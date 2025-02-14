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
 * @package    enrol_workdaystudent
 * @copyright  2023 onwards LSU Online & Continuing Education
 * @copyright  2023 onwards Robert Russo
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded

defined('MOODLE_INTERNAL') || die();

// The basics.
$string['pluginname'] = 'Workday Student Enrollment';
$string['pluginname_desc'] = 'LSU Workday Student Enrollment';
$string['reprocess'] = 'Reprocess Workday Student';
$string['workdaystudent:reprocess'] = 'Reprocess Workday Student';
$string['workdaystudent:delete'] = 'Delete Workday Student';
$string['workdaystudent:showhide'] = 'Show/Hide Workday Student';

// Basic Settings.
$string['workdaystudent:pluginsettings'] = 'Plugin Settings';
$string['workdaystudent:webservices'] = 'Webservice Settings';
$string['workdaystudent:emails'] = 'Administrative Contacts';

// The tasks.
$string['workdaystudent_full_enroll'] = 'Workday Student Enrollment';
$string['workdaystudent_quick_enroll'] = 'Quicker Workday Student Enrollment';

// Authentication Settings.
$string['workdaystudent_username'] = 'Username';
$string['workdaystudent_username_help'] = 'Username supplied by the webservice creator.';
$string['workdaystudent_password'] = 'Password';
$string['workdaystudent_password_help'] = 'Password supplied by the webservice creator.';

// Basic Config.
$string['workdaystudent_apiversion'] = 'API Version';
$string['workdaystudent_apiversion_help'] = 'Workday Student API version.';
$string['workdaystudent_campus'] = 'Campus Code';
$string['workdaystudent_campus_help'] = 'Campus code supplied by Workday Student contact.';
$string['workdaystudent_campusname'] = 'Campus Name';
$string['workdaystudent_campusname_help'] = 'Campus name supplied by Workday Student contact.';
$string['workdaystudent_semrange'] = 'Date Range';
$string['workdaystudent_semrange_help'] = 'Number of days ahead for grabbing future semesters.';
$string['workdaystudent_metafields'] = 'Meta Fields';
$string['workdaystudent_metafields_help'] = 'Comma separated list of meta fields to fetch.';
$string['workdaystudent_sportfield'] = 'Sport Field';
$string['workdaystudent_sportfield_help'] = 'Sport field designator.';
$string['workdaystudent_studentrole'] = 'Student Role';
$string['workdaystudent_studentrole_help'] = 'The role you want to use for students in the student courses.';
$string['workdaystudent_suspend_unenroll'] = 'Unenroll or Suspend';
$string['workdaystudent_suspend_unenroll_help'] = 'Unenroll or suspend students.';
$string['workdaystudent_contacts'] = 'Email Contacts';
$string['workdaystudent_contacts_help'] = 'Comma separated list of Moodle usernames you wish to email statuses and errors.';

// Webservice URL and endpoint suffixes.
$string['workdaystudent_wsurl'] = 'Webservice Endpoint';
$string['workdaystudent_wsurl_help'] = 'Base URL for the webservice endpoint.';
$string['workdaystudent_units'] = 'Units Endpoint';
$string['workdaystudent_units_help'] = 'URL suffix for the academic units endpoint.';
$string['workdaystudent_periods'] = 'Periods Endpoint';
$string['workdaystudent_periods_help'] = 'URL suffix for the academic periods endpoint.';
$string['workdaystudent_programs'] = 'Programs Endpoint';
$string['workdaystudent_programs_help'] = 'URL suffix for the programs of study endpoint.';
$string['workdaystudent_grading_schemes'] = 'Grading Schemes Endpoint';
$string['workdaystudent_grading_schemes_help'] = 'URL suffix for the grading schemes endpoint.';
$string['workdaystudent_courses'] = 'Courses Endpoint';
$string['workdaystudent_courses_help'] = 'URL suffix for the courses endpoint.';
$string['workdaystudent_sections'] = 'Sections Endpoint';
$string['workdaystudent_sections_help'] = 'URL suffix for the course sections endpoint.';
$string['workdaystudent_dates'] = 'Dates Endpoint';
$string['workdaystudent_dates_help'] = 'URL suffix for the academic dates endpoint.';
$string['workdaystudent_students'] = 'Students Endpoint';
$string['workdaystudent_students_help'] = 'URL suffix for the students endpoint.';
$string['workdaystudent_registrations'] = 'Registrations Endpoint';
$string['workdaystudent_registrations_help'] = 'URL suffix for the student section registrations endpoint.';
$string['workdaystudent_guild'] = 'GUILD Endpoint';
$string['workdaystudent_guild_help'] = 'URL suffix for the GUILD associations endpoint.';

// Emails.
$string['workdaystudent_emailname'] = 'WorkdayStudent Enrollment Administrator';
