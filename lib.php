<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package    enrol_workdaystudent
 * @copyright  2023 onwards LSU Online & Continuing Education
 * @copyright  2023 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_workdaystudent_plugin extends enrol_plugin {

    /**
     * Fetches the moodle "scheduled task" object
     *
     * @return \core\task\scheduled_task
     */
    private function get_scheduled_task() {
        $task = \core\task\manager::get_scheduled_task('\enrol_workdaystudent\task\workdaystudent_full_enroll');

        return $task;
    }

    /**
     * Master method for kicking off Workday Student enrollment
     *
     * @return boolean
     */
    public static function run_workdaystudent_full_enroll($courseid=null) {
        global $CFG;
        require_once('classes/workdaystudent.php');

        // Get settings. We ahve to do this several times as I overload them.
        $s = workdaystudent::get_settings();

        // Set the start time.
        $starttime = microtime(true);

        mtrace("Starting Moodle Student enrollments.");

        // Begin processing units.
        mtrace(" Fetching units from webserice.");

        // Set the satart time for the units fetch.
        $unitstart = microtime(true);

        // Fetch units.
        $units = workdaystudent::get_units($s);

        // How many units did we grab.
        $numunits = count($units);

        // Set the end time for the units fetch.
        $unitsend = microtime(true);

        // Calculate the units fetch time.
        $unitselapsed = round($unitsend - $unitstart, 2); 
        
        mtrace(" Fetched $numunits units from webserice in $unitselapsed seconds.");

        mtrace(" Processing $numunits units from webserice.");

        // Set the satart time for the units processing.
        $unitsptart = microtime(true);

        // Loop through the units.
        foreach ($units as $unit) {

            // Process the units.
            $academicunitid = workdaystudent::insert_update_unit($s, $unit);
        }

        // Set the end time for the units processing.
        $unitspend = microtime(true);

        if ($CFG->debugdisplay == 1) {
            mtrace(" Processed $numunits units in $unitselapsed seconds.");
        } else {
            mtrace("\n Processed $numunits units in $unitselapsed seconds.");
        }






        // Reset the counter. I hate this but I hate the weird logs more.
        workdaystudent::resetdtc();

        // Begin processing academic periods.
        mtrace(" Begin processing academic periods for institutional units.");

        // Get settings. We ahve to do this several times as I overload them.
        $s = workdaystudent::get_settings();

        // Set the period processing start time.
        $periodstart = microtime(true);

        // Get the local academic units.
        $lunits = workdaystudent::get_local_units($s);

        // Set up the date parms.
        $parms = workdaystudent::get_dates();

        // Set these up for later.
        $processedunits = count($lunits);
        $numperiods = 0;
        $totalperiods = 0;

        // Loop through all the the  units.
        foreach($lunits as $unit) {

            mtrace("  Begin processing periods for $unit->academic_unit_code - " .
                "$unit->academic_unit_id: $unit->academic_unit.");

            // In case something stupid happens, only process institutional units.
            if ($unit->academic_unit_subtype == "Institution") {

                // Add the relavent options to the date parms.
                $parms['Institution!Academic_Unit_ID'] = $s->campus;
                $parms['format'] = 'json';

                // Build the url into settings.
                $s = workdaystudent::buildout_settings($s, "periods", $parms);

                // Get the academic periods.
                $periods = workdaystudent::get_data($s);

                $numperiods = count($periods);
                $totalperiods = $totalperiods + $numperiods;

                foreach ($periods as $period) {
                    $indent = "   ";
                    workdaystudent::dtrace("$indent" . "Processing $period->Academic_Period_ID: " .
                        "$period->Name for $unit->academic_unit_id: $unit->academic_unit.", $indent);

                    // Get ancillary dates for census and post grades.
                    $pdates = workdaystudent::get_period_dates($s, $period);


                    // Check to see if we have a matching period.
                    $ap = workdaystudent::insert_update_period($s, $period);

                    foreach ($pdates as $pdate) {
                        // Set the academic period id to the pdate.
                        $pdate->academic_period_id = $period->Academic_Period_ID;
                        // Check to see if we have a matching period date entry.
                        $date = workdaystudent::insert_update_period_date($s, $pdate);
                    }
                    workdaystudent::dtrace("$indent" . "Finished processing $period->Academic_Period_ID: " . 
                        "$period->Name for $unit->academic_unit_id: $unit->academic_unit.", $indent);
                }
            }

            if ($CFG->debugdisplay == 1) {
                mtrace("  Finished processing $numperiods periods for " .
                    "$unit->academic_unit_id: $unit->academic_unit.");
            } else {
                mtrace("\n  Finished processing $numperiods periods for " .
                    "$unit->academic_unit_id: $unit->academic_unit.");
            }
        }

        $periodsend = microtime(true);
        $periodstime = round($periodsend - $periodstart, 2);
        mtrace(" Finished processing $totalperiods periods across " .
            "$processedunits units in $periodstime seconds.");







        $endtime = microtime(true);
        $elapsedtime = round($endtime - $starttime, 2);

        mtrace("Finished processing Moodle Student enrollments in $elapsedtime seconds.");
    }

    /**
     * Typical error log
     *
     * @var array
     */
    private $errors = array();

    /**
     * Typical email log
     *
     * @var array
     */
    private $emaillog = array();

    /**
     * Emails a workdaystudent report to Student administrators.
     *
     * @param  @int $starttime
     * @param  @object $s
     * @return void
     */
    private function get_workdaystudent_contacts($s, $starttime, $log) {
        global $DB;
        // Get all Student admins defined in settings.
        $usernames = explode($s->contacts);

        // Create a blank array for storing the users.
        $users = array();

        // Loop through the usernames and grab the users.
        foreach ($usernames as $username) {
            $users[] = $DB->get_records('user', array('username' => $username));
        }

        // Email these users the workdaystudent log.
        $this->email_workdaystudent_report_to_users($users, $starttime, $log);
    }

    /**
     * Emails a workdaystudent report to Student contacts.
     * (notification of start time, elapsed time, enrollments, unenrollments)
     *
     * @param  @array  $users
     * @param  @int $starttime
     * @param  @array $log
     * @return void
     */
    private function email_workdaystudent_report_to_users($users, $starttime, $log) {
        global $CFG;

        $starttimedisplay = $this->format_time_display($starttime);

        // Get email content from email log.
        $emailcontent = 'This email is to let you know that Workday Student Enrollment has begun at:' . $starttimedisplay;

        // Send to each admin.
        foreach ($users as $user) {
            email_to_user($user, ues::_s('pluginname'), sprintf('Workday Student Enrollment Begun [%s]', $CFG->wwwroot), $emailcontent);
        }
    }

    /**
     * Formats a Unix time for display
     *
     * @param  @int $time
     * @return @string $formatted
     */
    private function format_time_display($time) {
        $dformat = "l jS F, Y - H:i:s";
        $msecs = $time - floor($time);
        $msecs = substr($msecs, 1);

        $formatted = sprintf('%s%s', date($dformat), $msecs);

        return $formatted;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param  @object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/workdaystudent:delete', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param  @object $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/workdaystudent:showhide', $context);
    }
}

/**
 * This function extends the course navigation with the bulkenrol item
 *
 * @param  @object $navigation
 * @param  @object $course
 * @param  @object $context
 * @return @object $navigation node
 */
function enrol_workdaystudent_extend_navigation_course($navigation, $course, $context) {
    // Make sure we can reprocess enrollments.
    if (has_capability('enrol/workdaystudent:reprocess', $context)) {

        // Set the url for the reprocesser.
        $url = new moodle_url('/enrol/workdaystudent/reprocess.php', array('courseid' => $course->id));

        // Build the navigation node.
        $workdaystudentenrolnode = navigation_node::create(get_string('reprocess', 'enrol_workdaystudent'), $url,
                navigation_node::TYPE_SETTING, null, 'enrol_workdaystudent', new pix_icon('t/enrolusers', ''));

        // Set the users' navigation node.
        $usersnode = $navigation->get('users');

        // If we have an reprocess node, add it to the users' node.
        if (isset($workdaystudentenrolnode) && !empty($usersnode)) {

            // Actually add the node.
            $usersnode->add_node($workdaystudentenrolnode);
        }
    }
}
