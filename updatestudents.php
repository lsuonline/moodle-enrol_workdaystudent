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
 * @copyright  2023 Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/enrol/workdaystudent/classes/workdaystudent.php');

admin_externalpage_setup('update_students');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/enrol/workdaystudent/updatestudents.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'enrol_workdaystudent'));
$PAGE->set_heading(get_string('wds:updatestudents', 'enrol_workdaystudent'));

if (optional_param('runupdate_students', 0, PARAM_BOOL)) {
    require_sesskey();

    try {
        $updated = workdaystudent::mass_mstudent_updates();

        if ($updated) {
            redirect($url, get_string('wds:massupdate_ssuccess', 'enrol_workdaystudent'), null,
                core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($url, get_string('wds:massupdate_sfail', 'enrol_workdaystudent'), null,
                core\output\notification::NOTIFY_ERROR);
        }
    } catch (dml_exception $e) {
        redirect($url, get_string('wds:massupdate_dberror', 'enrol_workdaystudent', $e->getMessage()), null,
            core\output\notification::NOTIFY_ERROR);
    }
}

if (optional_param('runupdate_teachers', 0, PARAM_BOOL)) {
    require_sesskey();

    try {
        $updated = workdaystudent::mass_mteacher_updates();

        if ($updated) {
            redirect($url, get_string('wds:massupdate_fsuccess', 'enrol_workdaystudent'), null,
                core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($url, get_string('wds:massupdate_ffail', 'enrol_workdaystudent'), null,
                core\output\notification::NOTIFY_ERROR);
        }
    } catch (dml_exception $e) {
        redirect($url, get_string('wds:massupdate_dberror', 'enrol_workdaystudent', $e->getMessage()), null,
            core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('wds:updatestudents', 'enrol_workdaystudent'));
$runurlstudents = new moodle_url($url, ['runupdate_students' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->single_button($runurlstudents, get_string('wds:runstudentupdate', 'enrol_workdaystudent'));
echo html_writer::tag('p', get_string('wds:updatestudents_desc', 'enrol_workdaystudent'));
echo html_writer::empty_tag('br');

echo $OUTPUT->heading(get_string('wds:updateteachers', 'enrol_workdaystudent'));
$runurlteachers = new moodle_url($url, ['runupdate_teachers' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->single_button($runurlteachers, get_string('wds:runteacherupdate', 'enrol_workdaystudent'));
echo html_writer::tag('p', get_string('wds:updateteachers_desc', 'enrol_workdaystudent'));

echo $OUTPUT->footer();
