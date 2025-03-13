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

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $studentroles = array();

    if (isset($CFG->gradebookroles)) {
        // Get the "student" roles.
        $roles = explode(',', $CFG->gradebookroles);
    } else {
        $roles = array();
    }

    // Loop through those roles and do stuff.
    foreach ($roles as $role) {
        // Grab the role names from the DB.
        $rname = $DB->get_record('role', array("id" => $role), "shortname");
        // Set the studentroles array for the dropdown.
        $studentroles[$role] = $rname->shortname;
    }

    // Grab the course categories.
    $ccategories = $DB->get_records('course_categories', null, 'name', 'id,name');

    // Loop through those roles and do stuff.
    foreach ($ccategories as $category) {
        // Set the studentroles array for the dropdown.
        $categories[$category->id] = $category->name;
    }

    // Add a heading.
    $settings->add(
        new admin_setting_heading(
            'enrol_workdaystudent/pluginsettings',
            '',
            get_string('workdaystudent:pluginsettings', 'enrol_workdaystudent')
        )
    );

    // Workday Student Websevice username.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/username',
            get_string('workdaystudent_username', 'enrol_workdaystudent'),
            get_string('workdaystudent_username_desc', 'enrol_workdaystudent'),
            'Moodle_ISU', PARAM_TEXT
        )
    );

    // Workday Student Webservice Token.
    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_workdaystudent/password',
            get_string('workdaystudent_password', 'enrol_workdaystudent'),
            get_string('workdaystudent_password_desc', 'enrol_workdaystudent'),
            '', PARAM_RAW
        )
    );

    // Workday Student API Version.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/apiversion',
            get_string('workdaystudent_apiversion', 'enrol_workdaystudent'),
            get_string('workdaystudent_apiversion_desc', 'enrol_workdaystudent'),
            '43.0', PARAM_TEXT
        )
    );

    // Workday Student campus code.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/campus',
            get_string('workdaystudent_campus', 'enrol_workdaystudent'),
            get_string('workdaystudent_campus_desc', 'enrol_workdaystudent'),
            'AU00000079', PARAM_TEXT
        )
    );

    // Workday Student campus name.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/campusname',
            get_string('workdaystudent_campusname', 'enrol_workdaystudent'),
            get_string('workdaystudent_campusname_desc', 'enrol_workdaystudent'),
            'LSUAM', PARAM_TEXT
        )
    );

    // Workday semester range.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/brange',
            get_string('workdaystudent_brange', 'enrol_workdaystudent'),
            get_string('workdaystudent_brange_desc', 'enrol_workdaystudent'),
            '60', PARAM_INT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/erange',
            get_string('workdaystudent_erange', 'enrol_workdaystudent'),
            get_string('workdaystudent_erange_desc', 'enrol_workdaystudent'),
            '6', PARAM_INT
        )
    );


    // Workday student metadata fields.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/metafields',
            get_string('workdaystudent_metafields', 'enrol_workdaystudent'),
            get_string('workdaystudent_metafields_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student metadata fields.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/sportfield',
            get_string('workdaystudent_sportfield', 'enrol_workdaystudent'),
            get_string('workdaystudent_sportfield_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Student role.
    $settings->add(
        new admin_setting_configselect(
            'enrol_workdaystudent/studentrole',
            get_string('workdaystudent_studentrole', 'enrol_workdaystudent'),
            get_string('workdaystudent_studentrole_desc', 'enrol_workdaystudent'),
            'Student',  // Default.
            $studentroles
        )
    );

    // Suspend or Unenroll.
    $settings->add(
        new admin_setting_configselect(
            'enrol_workdaystudent/unenroll',
            get_string('workdaystudent_suspend_unenroll', 'enrol_workdaystudent'),
            get_string('workdaystudent_suspend_unenroll_desc', 'enrol_workdaystudent'),
            0,  // Default.
            array(0 => 'suspend', 1 => 'unenroll')
        )
    );

    // Add the course number creation threshold.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/numberthreshold',
            get_string('workdaystudent:numberthreshold', 'enrol_workdaystudent'),
            get_string('workdaystudent:numberthreshold_desc', 'enrol_workdaystudent'),
            9000,
            PARAM_INT
        )
    );

    // Course visibility defaults.
    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_workdaystudent/visible',
            get_string('workdaystudent:visible', 'enrol_workdaystudent'),
            get_string('workdaystudent:visible_desc', 'enrol_workdaystudent'),
            0
        )
    );

    // Course grouping defaults.
    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_workdaystudent/course_grouping',
            get_string('workdaystudent:course_grouping', 'enrol_workdaystudent'),
            get_string('workdaystudent:course_grouping_desc', 'enrol_workdaystudent'),
            1
        )
    );

    // Course unenrollment/suspension defaults.
    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_workdaystudent/suspend',
            get_string('workdaystudent:suspend', 'enrol_workdaystudent'),
            get_string('workdaystudent:suspend_desc', 'enrol_workdaystudent'),
            0
        )
    );

    // Add a heading.
    $settings->add(
        new admin_setting_heading(
            'enrol_workdaystudent/webservices',
            '',
            get_string('workdaystudent:webservices', 'enrol_workdaystudent')
        )
    );

    // Workday Student Websevice URL.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/wsurl',
            get_string('workdaystudent_wsurl', 'enrol_workdaystudent'),
            get_string('workdaystudent_wsurl_desc', 'enrol_workdaystudent'),
            'https://someurl.net', PARAM_URL
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/units',
            get_string('workdaystudent_units', 'enrol_workdaystudent'),
            get_string('workdaystudent_units_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/periods',
            get_string('workdaystudent_periods', 'enrol_workdaystudent'),
            get_string('workdaystudent_periods_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/programs',
            get_string('workdaystudent_programs', 'enrol_workdaystudent'),
            get_string('workdaystudent_programs_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/grading_schemes',
            get_string('workdaystudent_grading_schemes', 'enrol_workdaystudent'),
            get_string('workdaystudent_grading_schemes_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/courses',
            get_string('workdaystudent_courses', 'enrol_workdaystudent'),
            get_string('workdaystudent_courses_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/sections',
            get_string('workdaystudent_sections', 'enrol_workdaystudent'),
            get_string('workdaystudent_sections_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/dates',
            get_string('workdaystudent_dates', 'enrol_workdaystudent'),
            get_string('workdaystudent_dates_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/students',
            get_string('workdaystudent_students', 'enrol_workdaystudent'),
            get_string('workdaystudent_students_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/registrations',
            get_string('workdaystudent_registrations', 'enrol_workdaystudent'),
            get_string('workdaystudent_registrations_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Workday student webservice endpoints.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/guild',
            get_string('workdaystudent_guild', 'enrol_workdaystudent'),
            get_string('workdaystudent_guild_desc', 'enrol_workdaystudent'),
            '', PARAM_TEXT
        )
    );

    // Add a heading.
    $settings->add(
        new admin_setting_heading(
            'enrol_workdaystudent/coursedefs',
            '',
            get_string('workdaystudent:coursedefs', 'enrol_workdaystudent')
        )
    );

    // Add the course name seting.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/namingformat',
            get_string('workdaystudent:coursenamingformat', 'enrol_workdaystudent'),
            get_string('workdaystudent:coursenamingformat_desc', 'enrol_workdaystudent'),
            '{period_year} {period_type} {course_subject_abbreviation} {course_number} for {firstname} {lastname} {delivery_mode}',
            PARAM_TEXT
        )
    );

    // Add a heading.
    $settings->add(
        new admin_setting_heading(
            'enrol_workdaystudent/emails',
            '',
            get_string('workdaystudent:emails', 'enrol_workdaystudent')
        )
    );

    // Workday Student Administrative contacts.
    $settings->add(
        new admin_setting_configtext(
            'enrol_workdaystudent/contacts',
            get_string('workdaystudent_contacts', 'enrol_workdaystudent'),
            get_string('workdaystudent_contacts_desc', 'enrol_workdaystudent'),
            'admin,student', PARAM_TEXT
        )
    );

}
