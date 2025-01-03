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
 * @package    ues_reprocess
 * @copyright  2024 onwards LSUOnline & Continuing Education
 * @copyright  2024 onwards Robert Russo
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Make sure this can only run via CLI.
define('CLI_SCRIPT', true);

// Include the main Moodle config.
require_once(__DIR__ . '/../../../../config.php');

// Include the Workday Student helper class.
require_once(__DIR__ . '/../workdaystudent.php');

// Get settings.
$s = workdaystudent::get_settings();

// If we want to grab all campuses.
// unset($s->campus);

$periods = workdaystudent::get_current_periods($s);

$numgrabbed = 0;

foreach($periods as $period) {
    // Set upo the parameter array.
    $parms = array();

    // Add the academic period id.
    $parms['Academic_Period!Academic_Period_ID'] = $period->academic_period_id;

    // Set up some timing.
    $grabstart = microtime(true);

    // Get the sections.
    $sections = workdaystudent::get_sections($s, $parms);

    // Count how many sections we grabbed for this period.
    $numgrabbedperiod = count($sections);

    // Add them up in a self referential variable.
    $numgrabbed = $numgrabbedperiod + $numgrabbed;

    // Time how long grabbing the data from the WS took.
    $grabend = microtime(true);
    $grabtime = round($grabend - $grabstart, 2);
    mtrace("Fetched $numgrabbedperiod sections from $period->academic_period_id in $grabtime seconds.");

    // Set up some timing.
    $processstart = microtime(true);

    // Loop through the sections.
    foreach ($sections as $section) {

        // Insert or update this section.
        $sec = workdaystudent::insert_update_section($section);

        // If we do not have an instructor, let us know.
        if (!isset($section->Instructor_Info)) {
            mtrace(" - No instructors in $section->Section_Listing_ID.");

            // We don't have any instructors listed at the SIS, so unenroll any we might have already there.
            $enrollment = workdaystudent::insert_update_teacher_enrollment(
                $section->Section_Listing_ID,
                $tid = null,
                $role = null, 'unenroll');

        // If we have multiple instructors listed, deal with it.
        } else if (count($section->Instructor_Info) > 1) {
            mtrace(" - More than 1 instructor in $section->Section_Listing_ID.");

            // Loop through the instructors.
            foreach ($section->Instructor_Info as $teacher) {

                // Set some variables for later use.
                $secid = $section->Section_Listing_ID;
                $tid = $teacher->Instructor_ID;
                $pmi = isset($section->PMI_Universal_ID) ? $section->PMI_Universal_ID : null;
                $status = 'enroll';

                // If we have a primary instructor.
                if (!is_null($pmi)) {
                    mtrace("Primary instructor $pmi found!");

                    // Set the role to primary if the teacher matches the pmi.
                    $role = $tid == $pmi ? 'primary' : 'teacher';

                    // Set the teacher id to either the teacher id or primary id depending on what we have.
                    $tid = $tid == $pmi ? $pmi : $tid;

                // We don't have a primary, only multiple non-primaries.
                } else {
                    mtrace("More than one instructor in $secid and $tid is non-primary.");

                    // Set the role to non-primary.
                    $role = 'teacher';
                }

                // Update the teacher user info.
                $iteacher = workdaystudent::create_update_iteacher($s, $teacher);

                // Update the teacher enrollment db.
                $enrollment = workdaystudent::insert_update_teacher_enrollment($secid, $tid, $role, $status);
            }

        // We only have one instructor.
        } else {

            // Set some variables for later use.
            $teacher = $section->Instructor_Info[0];
            $secid = $section->Section_Listing_ID;
            $tid = $teacher->Instructor_ID;
            $pmi = isset($section->PMI_Universal_ID) ? $section->PMI_Universal_ID : null;
            $status = 'enroll';

            // If we have a primary instructor.
            if (!is_null($pmi)) {
                mtrace("Primary instructor $pmi found!");

                // Set the role to primary if the teacher matches the pmi.
                $role = $tid == $pmi ? 'primary' : 'teacher';

                // Set the teacher id to either the teacher id or primary id depending on what we have.
                $tid = $tid == $pmi ? $pmi : $tid;

            // We don't have a primary.
            } else {
                mtrace("Sole instructor in $secid and $tid is non-primary.");

                // Set the role to non-primary.
                $role = 'teacher';
            }

            // Update the teacher user info.
            $iteacher = workdaystudent::create_update_iteacher($s, $teacher);

            // Update the teacher enrollment db.
            $enrollment = workdaystudent::insert_update_teacher_enrollment($secid, $tid, $role, $status);
        }
    }
}
$processend = microtime(true);
$processtime = round($processend - $processstart, 2);
mtrace("Processing $numgrabbed sections took $processtime seconds.");
