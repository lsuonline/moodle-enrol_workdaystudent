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
 * @copyright 2024 onwards LSUOnline & Continuing Education
 * @copyright 2024 onwards Robert Russo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 */
class workdaystudent {

    // Start the dtrace counter.
    private static $dtc = 0;

    // Reset this after each loop is done.
    public static function resetdtc() {
        self::$dtc = 0;
    }

    /**
     * Grabs the settings from config_plugins.
     *
     * @return @object $s
     */
    public static function get_settings() {
        $s = new stdClass();

        // Get the settings.
        $s = get_config('enrol_workdaystudent');

        return $s;
    }

    public static function get_students($s, $periodid, $studentid) {
        // Log what we're doing.
        mtrace("Fetching students from webservice endpoint.");

        // Set the start time.
        $starttime = microtime(true);

        // Set the endpoint.
        $endpoint = 'students';

        // Set some aprms up.
        $parms = array();

        // Set the required campus id.
        $parms['Institution!Academic_Unit_ID'] = $s->campus;

        // Set the required term.
        $parms['Academic_Period!Academic_Period_ID'] = $periodid;

        // Set the Student ID if we're looking up a single student.
        if ($studentid !== '') {
            $parms['Universal_Id'] = $studentid;
        }

        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $s = self::buildout_settings($s, $endpoint, $parms);

        // Get the data.
        $students = self::get_data($s);

        if (!is_array($students) || empty($students)) {
            mtrace("$periodid contains no students, this is probably a sub-semester.");
            return false;
        }

        // Get a count of students.
        $studentcount = count($students);

        // Populate some stuff for later.
        $students[0]->studentcount = $studentcount;

        // Set the endtime.
        $endtime = microtime(true);

        // Calculate the elapsed time.
        $elapsedtime = round($endtime - $starttime, 1);

        // Log what we did.
        mtrace("Retreived $studentcount students in $elapsedtime seconds.");

        // Return the data.
        return $students;
    }

    public static function get_guild($s) {
        // Set the endpoint.
        $endpoint = 'guild';

        // Set some parms up.
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $bs = self::buildout_settings($s, $endpoint, $parms);

        // Get the units.
        $guilds = self::get_data($bs);

        return $guilds;
    }

    public static function get_uid_sfpr($guild) {
        $student = $guild->SFPR_Student;

        // Get the UID.
        preg_match('/(.+)\((\d+)\)/', $student, $matches);

        // If we found one, overwrite it appropriately.
        $guild->SFPR_StudentName = isset($matches[1]) ? trim($matches[1]) : $guild->SFPR_Student;
        $guild->SFPR_UID = isset($matches[2]) ? $matches[2] : $guild->SFPR_Student;

        // Return the object.
        return $guild;
    }

    public static function get_period_dates($s, $period) {
        // Set the endpoint.
        $endpoint = 'dates';

        // Set some parms up.
        $parms = array();
        $parms['Academic_Period!Academic_Period_ID'] = $period->Academic_Period_ID;
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $bs = self::buildout_settings($s, $endpoint, $parms);

        // Get the units.
        $dates = self::get_data($bs);

        return $dates;
    }

    public static function get_pg_dates($s, $period) {
        // Set the endpoint.
        $endpoint = 'dates';

        // Set some parms up.
        $parms = array();
        $parms['Academic_Period!Academic_Period_ID'] = $period->Academic_Period_ID;
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $bs = self::buildout_settings($s, $endpoint, $parms);

        // Get the units.
        $dates = self::get_data($bs);

        // Build the date object.
        $dateobj = new stdClass();
        $dateobjs = array();

        // Add the academic period id.
        $dateobj->academic_period_id = $period->Academic_Period_ID;

        foreach ($dates as $date) {
            // Fix the date and return an int in UTC.
            $realdate = self::format_pg_date($date->Date);

            $realdateobj = new DateTime($realdate);
            $timestamp = $realdateobj->getTimestamp();

            // Clean the academic level.
            $cleanal = str_replace(" ", "_", $date->Acad_Level);

            // Clean the date control.
            $cleanctrl = str_replace(' ', '_', $date->Date_Control);

            // Build the entry.
            $dateobj->{$cleanal . "_" . $cleanctrl} = $timestamp;
        //    $dateobjs[] = $dateobj;
        }

        return $dateobj;
    }

    // TODO: Deprecated, please remove.
    public static function clean_honors_grade($grade) {
        // First get universal ID.
        preg_match('/^(.+)\s+\((HNR)\)/', $grade, $matches);

        // Return the matches.
        return $matches[1];
    }

    public static function clean_sfpr_student($student) {
        // First get universal ID.
        preg_match('/^(.+)\s+\((\d+)\)/', $student, $matches);

        // Remove the 1st element.
        array_shift($matches);

        // Return the matches.
        return $matches;
    }


    public static function id_fake_courses($course) {
        // Identify courses with *s or all 0 as their course number.
        preg_match('/\*|0000/', $course->Course_Number, $match);

        // Return the matches.
        return $match;
    }

    public static function check_period($period) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_periods';

        // Set the parameters.
        $parms = array('academic_period_id' => $period->Academic_Period_ID);

        // Get the academic unit record.
        $ap = $DB->get_record($table, $parms);

        return $ap;
    }

    public static function check_period_date($date) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_pgc_dates';

        // Set the parameters.
        $parms = array('academic_period_id' => $date->academic_period_id,
            'academic_level' => $date->Acad_Level,
            'date_type' => $date->Date_Control);

        // Get the academic unit record.
        $ap = $DB->get_record($table, $parms);

        return $ap;
    }



    public static function check_unit($unit) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_units';

        // Set the parameters.
        $parms = array('academic_unit_id' => $unit->Academic_Unit_ID);

        // Get the academic unit record.
        $au = $DB->get_record($table, $parms);

        return $au;
    }

    public static function get_academic_year($period) {
        // Set this to avoid issues.8
        $ayear = array();

        if (!isset($period->Academic_Year)) {
            var_dump($period);
            return false;
        }

        // Find the year.
        preg_match('/(\d\d\d\d-\d\d\d\d).*/', $period->Academic_Year, $ayear);

        // Make sure we found the year.
        $academicyear = isset($ayear[1]) ? $ayear[1] : 0;

        //Return the year.
        return $academicyear;
    }

    public static function get_period_year($period) {
        if (isset($period->academic_period_id) && !isset($period->Academic_Period_ID)) {
            $period->Academic_Period_ID = $period->academic_period_id;
        } elseif (isset($period->Academic_Period_ID) && !isset($period->academic_period_id)) {
            $period->academic_period_id = $period->Academic_Period_ID;
        } else {
            mtrace("No academic period in the period, look into this");
            var_dump($period);
            return false;
        }

        // Find the year.
        preg_match('/.+?(\d\d\d\d).*/', $period->Academic_Period, $pyear);

        // Make sure we found the year.
        $periodyear = isset($pyear[1]) ? $pyear[1] : 0;

        //Return the year.
        return $periodyear;
    }

    public static function update_period($period, $ap) {
        global $DB;

        // Build the cloned object.
        $ap2 = clone($ap);

        // Set start dates.
        $startdate = strtotime($period->Start_Date);
        $enddate = strtotime($period->End_Date);

        // Get the period year.
        $periodyear = workdaystudent::get_period_year($period);

        // Get the academic year.
        $academicyear = workdaystudent::get_academic_year($period);

        // Keep the ids from $ap and populate the rest from $period.
        $ap2->academic_period_id = $period->Academic_Period_ID;
        $ap2->academic_period = $period->Academic_Period;
        $ap2->period_type = $period->Period_Type;
        $ap2->period_year = $periodyear;
        $ap2->academic_calendar = $period->Academic_Calendar;
        $ap2->academic_year = $academicyear;
        $ap2->start_date = (string) $startdate;
        $ap2->end_date = (string) $enddate;
        $ap2->enabled = $ap->enabled;

        // Compare the objects.
        if (get_object_vars($ap) === get_object_vars($ap2)) {
            self::dtrace("   - Academic period matched, skipping.");
            return $ap;
        } else {
            // Set the table.
            $table = 'enrol_oes_periods';

            // Update the record.
            $success = $DB->update_record($table, $ap2, false);

            if ($success) {
                self::dtrace("   - Academic period $ap->academic_period_id has been updated from the endpoint.");

                // Return the updated object.
                return $ap2;
            } else {
                mtrace("   - Updating $ap->academic_period_id failed and has not been updated.");

                // Return the original object.
                return $ap;
            }
        }
    }

    public static function insert_period($period) {
        global $DB;

        // Only deal with actual periods with years and dates associated with them.
        if (isset($period->Academic_Year) && (isset($period->academic_period_id) || isset($period->Academic_Period_ID))) {

            // Set the table.
            $table = 'enrol_oes_periods';

            // Create the object.
            $tap = new stdClass();

            // Set start dates.
            $startdate = strtotime($period->Start_Date);
            $enddate = strtotime($period->End_Date);

            // Get the period year.
            $periodyear = workdaystudent::get_period_year($period);

            // Get the academic year.
            $academicyear = workdaystudent::get_academic_year($period);

            // Populate the temporary period table.
            $tap->academic_period_id = isset($period->Academic_Period_ID) ? $period->Academic_Period_ID : $period->academic_period_id;
            $tap->academic_period = $period->Academic_Period;
            $tap->period_type = $period->Period_Type;
            $tap->period_year = $periodyear;
            $tap->academic_calendar = $period->Academic_Calendar;
            $tap->academic_year = $academicyear;
            $tap->start_date = $startdate;
            $tap->end_date = $enddate;

            $ap = $DB->insert_record($table, $tap);
            self::dtrace("   - Inserted academic_period_id: $tap->academic_period_id.");

            return $tap;
        } else {
            var_dump($period);
            return false;
        }
    }

    public static function insert_period_date($pdate) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_pgc_dates';

        // Create the object.
        $pd = new stdClass();

        // Populate the temporary period date table.
        $pd->academic_period_id = $pdate->academic_period_id;
        $pd->academic_level = $pdate->Acad_Level;
        $pd->date_type = $pdate->Date_Control;
        $pd->date = strtotime($pdate->Date);

        $ap = $DB->insert_record($table, $pd);
        self::dtrace("    - Inserted $pd->academic_level $pd->date_type for $pd->academic_period_id.");

        return $ap;
    }


    public static function update_period_date($pdate, $pd) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_pgc_dates';

        // Create the object.
        $pd2 = new stdClass();

        // Populate the temporary period date table.
        $pd2->id = $pd->id;
        $pd2->academic_period_id = $pdate->academic_period_id;
        $pd2->academic_level = $pdate->Acad_Level;
        $pd2->date_type = $pdate->Date_Control;
        $pd2->date = (string) strtotime($pdate->Date);

        if (get_object_vars($pd) !== get_object_vars($pd2)) {
            $ap = $DB->update_record($table, $pd2, false);
            self::dtrace("    - Updated $pd2->academic_level $pd2->date_type for $pd2->academic_period_id.");
            return $pd2;
        } else {
            self::dtrace("    - $pd2->academic_level $pd2->date_type records matched perfectly, skipping.");
            return $pd2;
        }
    }

    public static function check_section($section) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_sections';

        // Set the parameters.
        $parms = array('section_listing_id' => $section->Section_Listing_ID);

        // Get the academic unit record.
        $as = $DB->get_record($table, $parms);

        return $as;
    }


    public static function check_course($course) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_courses';

        // Set the parameters.
        $parms = array('course_listing_id' => $course->Course_Listing_ID);

        // Get the academic unit record.
        $ac = $DB->get_record($table, $parms);

        return $ac;
    }

    public static function update_section($section, $as) {
        global $DB;

        // Build the cloned object.
        $as2 = clone($as);

        // Keep id, section_listing_id, idnumber, and status from $as and populate the rest from $section.
        $as2->course_section_definition_id = $section->Course_Section_Definition_ID;
        $as2->section_number = $section->Section_Number;
        $as2->course_definition_id = $section->Course_Definition_ID;
        $as2->academic_unit_id = $section->Academic_Unit_ID;
        $as2->academic_period_id = $section->Academic_Period_ID;
        $as2->course_section_title = $section->Course_Section_Title;
        $as2->course_section_abbreviated_title = $section->Course_Section_Abbreviated_Title;
        $as2->delivery_mode = $section->Delivery_Mode;
        $as2->class_type = $section->Class_Type;
        $as2->controls_grading = $section->Controls_Grading;
        $as2->wd_status = isset($section->Course_Section_Status) ? $section->Course_Section_Status : 'Pending';
        $as2->moodle_status = 'Pending';

        // Compare the objects.
        if (get_object_vars($as) === get_object_vars($as2)) {
            self::dtrace("Section $section->Section_Listing_ID matched stored value, skipping.");

            return $as;
        } else {
            // Set the table.
            $table = 'enrol_oes_sections';

            // Update the record.
            $success = $DB->update_record($table, $as2, false);

            if ($success) {
                self::dtrace("Section $as->section_listing_id has been updated from the endpoint.");

                // Return the updated object.
                return $as2;
            } else {
                mtrace("Updating $as->section_listing_id failed and has not been updated.");

                // Return the original object.
                return $as;
            }
        }
    }

    public static function insert_section($section) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_sections';

        // Create the object.
        $tas = new stdClass();

        // Build the object from $section 
        $tas->section_listing_id = $section->Section_Listing_ID;
        $tas->course_section_definition_id = $section->Course_Section_Definition_ID;
        $tas->section_number = $section->Section_Number;
        $tas->course_definition_id = $section->Course_Definition_ID;
        $tas->academic_unit_id = $section->Academic_Unit_ID;
        $tas->academic_period_id = $section->Academic_Period_ID;
        $tas->course_section_title = $section->Course_Section_Title;
        $tas->course_section_abbreviated_title = $section->Course_Section_Abbreviated_Title;
        $tas->delivery_mode = $section->Delivery_Mode;
        $tas->class_type = $section->Class_Type;
        $tas->idnumber = null;
        $tas->controls_grading = $section->Controls_Grading;
        $tas->wd_status = isset($section->Course_Section_Status) ? $section->Course_Section_Status : 'Pending';

        $as = $DB->insert_record($table, $tas);
        self::dtrace("Inserted section_listing_id: $tas->section_listing_id.");

        return $as;
    }

    public static function update_course($course, $ac) {
        global $DB;

        // Build the cloned object.
        $ac2 = clone($ac);

        // Keep the id and course_listing_id from $ac and populate the rest from $course.
        $ac2->academic_unit_id = $course->Academic_Unit_ID;
        $ac2->course_definition_id = $course->Course_Definition_ID;
        $ac2->course_number = $course->Course_Number;
        $ac2->course_subject_abbreviation = $course->Course_Subject_Abbreviation;
        $ac2->course_subject = $course->Course_Subject;
        $ac2->subject_code = $course->Subject_Code;
        $ac2->course_abbreviated_title = $course->Course_Abbreviated_Title;
        $ac2->academic_level = $course->Academic_Level;

        // Compare the objects.
        if (get_object_vars($ac) === get_object_vars($ac2)) {
            self::dtrace("Course $ac->course_listing_id matched $course->Course_Listing_ID, skipping.");
            return $ac;
        } else {
            // Set the table.
            $table = 'enrol_oes_courses';

            // Update the record.
            $success = $DB->update_record($table, $ac2, false);

            if ($success) {
                self::dtrace("Academic unit $ac->course_listing_id has been updated from the endpoint.");

                // Return the updated object.
                return $ac2;
            } else {
                mtrace("Updating $ac->course_listing_id failed and has not been updated.");

                // Return the original object.
                return $ac;
            }
        }
    }

    public static function get_prevdays_date($xdays) {
        // Build the date.
        $date = new DateTime();

        // Set the timezone.
        $date->setTimezone(new DateTimeZone('America/Chicago'));

        // Modify the date.
        $date->modify('-' . $xdays . ' days');

        // Set the time to midnight.
        $date->setTime(0, 0);

        // Set the fdate variable for use.
        $fdate = $date->format('Y-m-d\TH:i:s');

        return $fdate;
    }

    public static function insert_course($course) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_courses';

        // Create the object.
        $tac = new stdClass();

        // Build the object from $unit.
        $tac->course_listing_id = $course->Course_Listing_ID;
        $tac->academic_unit_id = $course->Academic_Unit_ID;
        $tac->course_definition_id = $course->Course_Definition_ID;
        $tac->course_number = $course->Course_Number;
        $tac->course_subject_abbreviation = $course->Course_Subject_Abbreviation;
        $tac->course_subject = $course->Course_Subject;
        $tac->subject_code = $course->Subject_Code;
        $tac->course_abbreviated_title = $course->Course_Abbreviated_Title;
        $tac->academic_level = $course->Academic_Level;

        $ac = $DB->insert_record($table, $tac);
        self::dtrace("Inserted course_listing_id: $tac->course_listing_id.");

        return $ac;
    }

    public static function insert_update_student_enrollment($s, $enrollment, $unenrolls, $enrolls, $donothings) {
        // Enrollment is missing universal ID. Log and move on.
        if (!isset($enrollment->Universal_Id)) {
            $fullname = isset($enrollment->Full_Legal_Name) ? $enrollment->Full_Legal_Name : 'Someone';
            $email = isset($enrollment->LSUAM_Institutional_Email) ? $enrollment->LSUAM_Institutional_Email : $fullname;
            mtrace("$enrollment->Section_Listing_ID missing universal ID for $email.");
            return false;
        }

        // Check to see if the enrollment record exists.
        $as = self::check_student_enrollment($enrollment);

        $grading_basis = isset($enrollment->Grading_Basis) ? $enrollment->Grading_Basis : 'Graded';

        $gsid = isset($enrollment->Student_Grading_Scheme_ID)
            ? $enrollment->Student_Grading_Scheme_ID
            : $s->campusname . ' Standard Grading Scheme'; 

        // It exists and does not match registration status, update it.
        if (isset($as->id) && isset($enrollment->Registered_Date) && (
            $as->grading_scheme != $gsid ||
            $as->grading_basis != $grading_basis ||
            $as->credit_hrs != $enrollment->Units ||
            $as->registered_date != $enrollment->Registered_Date ||
            $as->registration_status != $enrollment->Registration_Status
        )) {
            self::dtrace("Found interstitial enrollment record that requires an update with id: $as->id.");
            $as = self::update_student_enrollment($s, $enrollment, $unenrolls, $enrolls, $donothings, $as);

        } else if (isset($as->id) && !isset($enrollment->Registered_Date) && (
            $as->grading_scheme != $gsid ||
            $as->grading_basis != $grading_basis ||
            $as->credit_hrs != $enrollment->Units ||
            $as->registration_status != $enrollment->Registration_Status
        )) {
            self::dtrace("Found interstitial enrollment record that requires an update with id: $as->id.");
            $as = self::update_student_enrollment($s, $enrollment, $unenrolls, $enrolls, $donothings, $as);

        // It does not exist, create it.
        } else if (!isset($as->id)) {
            $as = self::insert_student_enrollment($s, $enrollment, $unenrolls, $enrolls, $donothings);
            self::dtrace("No enrollment record for: $enrollment->Universal_Id in $enrollment->Section_Listing_ID. Created it with id $as.");

        // It exists and matches, log it.
        } else {
            self::dtrace("Found interstitial enrollment record with id: $as->id. No update required.");
        }

        return $as;
    }

    public static function dateconv($datestr) {
        // Build the string into a date.
        $date = new DateTime($datestr);

        // Convert the date into a timestamp.
        $timestamp = $date->getTimestamp();

        // Return it.
        return (string) $timestamp;
    }

    public static function update_student_enrollment($s, $enrollment, $unenrolls, $enrolls, $donothings, $as) {
        global $DB;

        // Figure out some dates in unix_timestamps.
        $wdate = isset($enrollment->Withdraw_Date)
                    ? self::dateconv($enrollment->Withdraw_Date)
                    : 0;
        $regdate = isset($enrollment->Registered_Date)
                   ? self::dateconv($enrollment->Registered_Date)
                   : 0;
        $dropdate = isset($enrollment->Drop_Date) 
                    ? self::dateconv($enrollment->Drop_Date)
                    : $wdate;
        $lastupdate = isset($enrollment->Last_Functionally_Updated)
                      ? self::dateconv($enrollment->Last_Functionally_Updated)
                      : time();

        // Build the cloned object.
        $as2 = clone($as);

        if (!isset($enrollment->Grading_Basis)) {
            mtrace("Grading basis not set for course: $enrollment->Section_Listing_ID and student: $enrollment->Universal_Id.");
        }

        // Keep the id, section_listing_id, and $universal_id from $as and populate the rest from aenrollment.
        $as2->credit_hrs = $enrollment->Units;
        $as2->grading_scheme = isset($enrollment->Student_Grading_Scheme_ID)
                               ? $enrollment->Student_Grading_Scheme_ID
                               :  $s->campusname . ' Standard Grading Scheme';
        $as2->grading_basis = isset($enrollment->Grading_Basis) ? $enrollment->Grading_Basis : 'Graded';
        $as2->registration_status = $enrollment->Registration_Status;
        $as2->registered_date = $regdate;
        $as2->drop_date = $dropdate;
        $as2->lastupdate = $lastupdate;

        // Status gets complicated and is based on Registration Status.
        if (in_array($enrollment->Registration_Status, $unenrolls) && $as->status != 'unenrolled') {
            $as2->status = 'unenroll';

        } else if (in_array($enrollment->Registration_Status, $unenrolls) && $as->status == 'unenrolled') {
            $as2->status = 'unenrolled';

        } else if (in_array($enrollment->Registration_Status, $enrolls) && $as->status != 'enrolled') {
            $as2->status = 'enroll';

        } else if (in_array($enrollment->Registration_Status, $enrolls) && $as->status == 'enrolled') {
            $as2->status = 'enrolled';

        } else {
            $as2->status = mb_strtolower($enrollment->Registration_Status, 'UTF-8');
        }

        $as2->prevstatus = $as->status;

        // Compare the objects.
        if (get_object_vars($as) === get_object_vars($as2)) {
            return $as;
        } else {
            // Set the table.
            $table = 'enrol_oes_student_enrollments';

            if ($as2->lastupdate >= $as->lastupdate) {
                // Update the record.
                $success = $DB->update_record($table, $as2, true);
            } else {
                self::dtrace("Enrollment record for $as->section_listing_id - $as->universal_id is older than the existing record, skipping.");
            }

            if (isset($success) && $success == true) {
                self::dtrace("Enrollment for $as2->universal_id in $as2->section_listing_id has been updated from the endpoint.");

                // Return the updated object.
                return $as2;
            } else if (isset($success) && $success == false) {
                self::dtrace("Enrollment for $as2->universal_id in $as2->section_listing_id failed updating status to: $as2->registration_status from the endpoint.");

                // Return the original object.
                return $as;
            } else {
                return $as;
            }
        }
    }



    public static function check_student_enrollment($enrollment) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_student_enrollments';

        // Set the parameters.
        $parms = array('section_listing_id' => $enrollment->Section_Listing_ID, 'universal_id' => $enrollment->Universal_Id);

        // Get the enrollment record.
        $as = $DB->get_record($table, $parms);

        return $as;
    }

    public static function insert_student_enrollment($s, $enrollment, $unenrolls, $enrolls, $donothings) {
        global $DB;

        // Figure out some dates in unix_timestamps.
        $wdate = isset($enrollment->Withdraw_Date)
                    ? self::dateconv($enrollment->Withdraw_Date)
                    : 0;
        $regdate = isset($enrollment->Registered_Date)
                   ? self::dateconv($enrollment->Registered_Date)
                   : 0;
        $dropdate = isset($enrollment->Drop_Date)
                    ? self::dateconv($enrollment->Drop_Date)
                    : $wdate;
        $lastupdate = isset($enrollment->Last_Functionally_Updated)
                      ? self::dateconv($enrollment->Last_Functionally_Updated)
                      : time();

        // Set the table.
        $table = 'enrol_oes_student_enrollments';

        // Create the object.
        $tas = new stdClass();

        // Build the object from $enrollment.
        $tas->section_listing_id = $enrollment->Section_Listing_ID;
        $tas->universal_id = $enrollment->Universal_Id;
        $tas->credit_hrs = $enrollment->Units;
        $tas->grading_scheme = isset($enrollment->Student_Grading_Scheme_ID)
                               ? $enrollment->Student_Grading_Scheme_ID
                               :  $s->campusname . ' Standard Grading Scheme';
        $tas->grading_basis = isset($enrollment->Grading_Basis) ? $enrollment->Grading_Basis : 'Graded';
        $tas->registration_status = $enrollment->Registration_Status;
        $tas->registered_date = $regdate;
        $tas->drop_date = $dropdate;
        $tas->lastupdate = $lastupdate;

        // Status gets complicated and is based on Registration Status.
        if (in_array($enrollment->Registration_Status, $unenrolls)) {
            $tas->status = 'unenrolled';
        } else if (in_array($enrollment->Registration_Status, $enrolls)) {
            $tas->status = 'enroll';
        } else {
            $tas->status = $enrollment->Registration_Status;
        }

        // This is the first status and should be null.
        $tas->prevstatus = null;

        // Insert the record.
        $as = $DB->insert_record($table, $tas);

        return $as;
    }



    public static function insert_update_course($s, $course) {
        $ac = self::check_course($course);
        if (isset($ac->id)) {
            $ac = self::update_course($course, $ac);
        } else {
            $ac = self::insert_course($course);
        }
        return $ac;
    }

    public static function insert_update_section($section) {
        $as = self::check_section($section);
        if (isset($as->id)) {
            $as = self::update_section($section, $as);
        } else {
            $as = self::insert_section($section);
        }
        return $as;
    }

    public static function insert_update_period($s, $period) {
        $ap = self::check_period($period);
        if (isset($ap->id)) {
            $ap = self::update_period($period, $ap);
        } else {
            $ap = self::insert_period($period);
        }
        return $ap;
    }

    public static function insert_update_period_date($s, $pdate) {
        $pd = self::check_period_date($pdate);

        if (isset($pd->id)) {
            $pd = self::update_period_date($pdate, $pd);
        } else {
            $pd = self::insert_period_date($pdate);
        }
        return $pd;
    }

    public static function update_unit($unit, $au) {
        global $DB;

        // Build the cloned object.
        $au2 = clone($au);

        // Keep the ids from $au and populate the rest from $unit.
        $au2->academic_unit_subtype = $unit->Academic_Unit_Subtype;
        $au2->academic_unit_code = $unit->Academic_Unit_Code;
        $au2->academic_unit = $unit->Academic_Unit;
        $au2->superior_unit_id = $unit->Superior_ID;

        // Compare the objects.
        if (get_object_vars($au) === get_object_vars($au2)) {
            self::dtrace(" - Academic unit $au->academic_unit_id matched $unit->Academic_Unit_ID, skipping.");
            return $au;
        } else {
            // Set the table.
            $table = 'enrol_oes_units';

            // Update the record.
            $success = $DB->update_record($table, $au2, false);

            if ($success) {
                self::dtrace(" - Academic unit $au->academic_unit_id has been updated from the endpoint.");

                // Return the updated object.
                return $au2;
            } else {
                mtrace(" - Updating $au->academic_unit_id failed and has not been updated.");

                // Return the original object.
                return $au;
            }
        }
    }

    public static function cleanxml($xmlstring) {
        // Use a regex to remove `{+1}` entirely.
        $xmlstring = preg_replace('/\{[^}]*\}/', '', $xmlstring);

        // Ensure that the XML is well-formed using DOMDocument.
        $dom = new DOMDocument('1.0', 'UTF-8');
        // Suppress warnings to handle them programmatically.
        libxml_use_internal_errors(true);

        // Load the XML string into the DOMDocument.
        if (!$dom->loadXML($xmlstring)) {
            // If there's an error loading XML, print the errors for debugging.
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                echo ("XML Error: " . $error->message . "\n");
            }

            libxml_clear_errors();

            // Return null if XML is invalid.
            return null;
        }

        // Format the output (pretty print) for easier reading.
        $dom->formatOutput = true;

        // Return the cleaned and formatted XML string.
        return $dom->saveXML();
    }

    public static function buildgradestopost($grades, $gradetype) {
        $today = date('Y-m-d');

        $studentgrades = '';
        foreach ($grades as $grade) {
            // Student Registration Data
            $sectionlistingid = $grade->section_listing_id;
            $universalid = $grade->universal_id;

            // Grade for the registration in question.
            $gradeid = $grade->grade_id;

            // Check to see if we're in finals or this is an interim grade. 
            if ($gradetype == "finals") {
                // Posting final grades.
                $sdtype = "Student_Grades_Data";

                // If we have a last date of attendance set, send it.
                if (isset($grade->requires_last_attendance)) {
                    $ld = date('Y-m-d', $grade->last_attendance_date);
                    $ldoa = "<wd:Student_Last_Date_of_Attendance>$ld</wd:Student_Last_Date_of_Attendance>";
                } else {
                    $ldoa = "";
                }

                // If we have an interim grade note, use it.
                if (isset($grade->grade_note_required)) {
                    $note = $grade->grade_note_required;
                    $gnote = "<wd:Student_Grade_Note>$note</wd:Student_Grade_Note>";
                } else {
                    $gnote = "";
                }

                $gdate = "";
            } else {
                // Posting interim grades.
                $sdtype = "Student_Interim_Grades_Data";

                // If we have an interim grade note, use it.
                if (isset($grade->grade_note_required)) {
                    $note = $grade->grade_note_required;
                    $gnote = "<wd:Student_Interim_Grade_Note>$note</wd:Student_Interim_Grade_Note>";
                } else {
                    $gnote = "";
                }

                // Set the interim grade date to today and send it.
                $gdate =  "<wd:Student_Interim_Grade_Date>$today</wd:Student_Interim_Grade_Date>";

                // Last date of attendance never required for interim grades?
                $ldoa = "";
            }

            // Build out the xml.
            $studentsgrade = '
                            <wd:' . $sdtype . '>
                                <wd:Student_Reference>
                                    <wd:ID wd:type="Universal_Identifier_ID">' . $universalid . '</wd:ID>
                                </wd:Student_Reference>
                                <wd:Student_Grade_Reference>
                                    <wd:ID wd:type="Student_Grade_ID">' . $gradeid . '</wd:ID>
                                </wd:Student_Grade_Reference>
                                ' . $gnote . '
                                ' . $gdate . '
                                ' . $ldoa . '
                            </wd:' . $sdtype . '>';

            // Send this to the $studentgrades loop.
            $studentgrades .= $studentsgrade;
        }

        return $studentgrades;
    }

    public static function parseerrors($data) {
        // Load the XML string into a SimpleXML object
        $xml = simplexml_load_string($data, null, LIBXML_NOCDATA);

        // Register the namespaces for XPath
        $xml->registerXPathNamespace('SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('wd', 'urn:com.workday/bsvc');

        // Find all Validation_Error elements
        $errors = $xml->xpath('//wd:Validation_Error');

        // Initialize an array to store the results
        $results = [];

        foreach ($errors as $error) {
            // Build out the object for inclusion in the array.
            $returnerror = new stdClass();

            // Get the message and xpath values.
            $message = (string) $error->xpath('wd:Message')[0];
            $xpath = (string) $error->xpath('wd:Xpath')[0];

            // Extract the index from the XPath
            preg_match('/Student_Grades_Data\[(\d+)\]/', $xpath, $matches);

            if (isset($matches[1])) {
                // The index inside the Student_Grades_Data[X].
                $index = $matches[1];
            } else {
                // In case we don't find the expected index.
                $index = 'unknown';
            }

            // Build out the returnerror object.
            $returnerror->index = $index;
            $returnerror->message = $message;

            // Store the result in the array
            $results[] = $returnerror;
        }

        // Return the results
        return $results;
    }

    public static function buildsoapxml($s, $grades, $gradetype, $sectionlistingid) {

        // Build out if it's interim or final grades.
        if ($gradetype == "finals") {
            $wdendpoint = "Submit_Grades_for_Registrations_Request";
            $wddata = "Submit_Grades_for_Registrations_Data";
            $bpparms = "<wd:Business_Process_Parameters>" . 
                "<wd:Auto_Complete>true</wd:Auto_Complete>" .
                "<wd:Run_Now>true</wd:Run_Now>" .
                "</wd:Business_Process_Parameters>";
        } else {
            $wdendpoint = "Put_Interim_Grades_for_Registrations_Request";
            $wddata = "Put_Interim_Grades_for_Registrations_Data";
            $bpparms = "";
        }

        // Workday API credentials
        $username = $s->username . "@lsu14";
        $password = $s->password;
        $version = "v" . $s->apiversion;

        // Build out the student grades portion of the xml.
        $gradesxml = self::buildgradestopost($grades, $gradetype);

        // Create SOAP Envelope
        $xml = new SimpleXMLElement('<env:Envelope
            xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                <env:Header>
                    <wsse:Security env:mustUnderstand="1">
                        <wsse:UsernameToken>
                            <wsse:Username>'
                                . $username .
                            '</wsse:Username>
                            <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'
                                . $password .
                            '</wsse:Password>
                        </wsse:UsernameToken>
                    </wsse:Security>
                </env:Header>
                <env:Body>
                    <wd:' . $wdendpoint . '
                        xmlns:wd="urn:com.workday/bsvc"
                        wd:version="' . $version . '">
                        ' . $bpparms .'
                        <wd:' . $wddata . '>
                            <wd:Section_Listing_Reference>
                                <wd:ID wd:type="Section_Listing_ID">' . $sectionlistingid . '</wd:ID>
                            </wd:Section_Listing_Reference>
                            ' . $gradesxml . '
                        </wd:' . $wddata . '>
                    </wd:' . $wdendpoint . '>
                </env:Body>
           </env:Envelope>');

        // Convert SimpleXMLElement to string
        $xmlstring = $xml->asXML();

        $xmlstr = self::cleanxml($xmlstring);
    
        // Return the XML as a string
        return $xmlstr;
    }

    public static function pg_section_status($errors) {

        // TODO: WTF am I going to do with this? There are actually 3 of these potential messages and I probably have to deal with all of them.
        $searchmessage1 = "The Student Course Section for the Section Listing must have already started to be valid for grading.";
        $searchmessage2 = "The Student Course Section for the Section Listing must be of the Instructional Format that controls grading.";
        $searchmessage3 = "The Student Course Section for the Section Listing must have Registered Student Course Registrations for grading.";

        // Load the XML string.
        $xml = simplexml_load_string($errors, null, LIBXML_NOCDATA);

        // Register the namespaces for XPath.
        $xml->registerXPathNamespace('SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('wd', 'urn:com.workday/bsvc');

        // Use XPath to find all <wd:Message> elements that contain the error message.
        $messages = $xml->xpath('//wd:Message');

        $found = false;
        // Loop through all messages to check if the target message exists.
        foreach ($messages as $message) {
            if ((string)$message == $searchmessage1) {
                $found = true;
                break;
            }
        }

        // Return true if found, false otherwise.
        return $found ? "true" : "false";
    }

    public static function post_grade($s, $grades, $gradetype, $sectionlistingid) {

        // Build out the xml.
        $xml = self::buildsoapxml($s, $grades, $gradetype, $sectionlistingid);

        // Workday API credentials
        $username = $s->username . "@lsu14";
        $password = $s->password;

        $version = "v" . $s->apiversion;

        // Workday API endpoint for the Submit_Grades_for_Registrations SOAP operation
        $workdayurl = "https://wd2-impl-services1.workday.com/ccx/service/lsu14/Student_Records/$version";

        // Initiate the curl handler.
        $ch = curl_init($workdayurl);

        // Set cURL options for curl request
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($xml)
        ]);

        // Execute cURL request
        $response = curl_exec($ch);


        // Get the http code for later.
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check if the cURL request was successful.
        if(curl_errno($ch)) {
            $curlerror = curl_error($ch);

            // Return the error.
            mtrace("cURL ERROR: $curlerror. Aborting.");
            return "error";

        // Check to see that we have a proper response.
        } else if ($httpcode != "200") {
            if ($httpcode != "500") {
                // Return the HTTP status code.
                mtrace("SERVER ERROR - HTTP Status Code: $httpcode. Aborting.");
                return "error";
            } else {
                // Clean the resulting response.
                $xmlstring = self::cleanxml($response);

                // Build an object to store the error code and XML string.
                $xmlobj = new stdClass();

                // Add the error.
                $xmlobj->error = $httpcode;

                // Add ths XML string.
                $xmlobj->xmlstring = $xmlstring;

                return $xmlobj;
            }
        }


        // Clean the resulting response.
        $xmlstring = self::cleanxml($response);

        return $xmlstring;
    }

    public static function insert_unit($unit) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_units';

        // Create the object.
        $tau = new stdClass();

        // Build the object from $unit.
        $tau->academic_unit_id = $unit->Academic_Unit_ID;
        $tau->academic_unit_subtype = $unit->Academic_Unit_Subtype;
        $tau->academic_unit_code = $unit->Academic_Unit_Code;
        $tau->academic_unit = $unit->Academic_Unit;
        $tau->superior_unit_id = isset($unit->Superior_ID) ? $unit->Superior_ID : '';

        $au = $DB->insert_record($table, $tau);
        self::dtrace("Inserted academic_unit_id: $tau->academic_unit_id.");

        return $au;
    }

    public static function insert_update_unit($s, $unit) {
        $au = self::check_unit($unit);
        if (isset($au->id)) {
            $au = self::update_unit($unit, $au);
        } else {
            $au = self::insert_unit($unit);
        }
        return $au;
    }

    public static function get_current_departments($s) {
        global $DB;

        $sql = 'SELECT CONCAT(p.id, "_",  c.id, "_", s.id) AS uniqueid, c.course_subject_abbreviation, p.academic_period_id 
            FROM {enrol_oes_periods} p
                INNER JOIN {enrol_oes_sections} s ON p.academic_period_id = s.academic_period_id
                INNER JOIN {enrol_oes_courses} c ON s.course_definition_id = c.course_definition_id
            WHERE p.start_date < UNIX_TIMESTAMP(NOW())
                AND p.end_date > UNIX_TIMESTAMP(NOW())
                AND p.enabled = 1
                GROUP BY c.course_subject_abbreviation, p.academic_period_id';

        // Get the data using the sql above.
        $departments = $DB->get_records_sql($sql);

/*
        // Extract the course subject abbreviations.
        $abbreviations = array_map(function($department) {
            return $department->course_subject_abbreviation;
        }, $departments);

        return $abbreviations;
*/
        return $departments;
    }

    public static function get_current_periods($s) {
        global $DB;

        // Set the semester range for getting future and recent semesters.
        $fsemrange = isset($s->semrange) ? ($s->semrange * 86400) : 0;
        $psemrange = isset($s->semrange) ? (($s->semrange / 6) * 86400) : 0;

        // Build the SQL.
        $sql = "SELECT p.academic_period_id
                  FROM mdl_enrol_oes_periods p
                WHERE p.enabled = 1
                  AND ((p.start_date < UNIX_TIMESTAMP() + $fsemrange
                  AND p.end_date > UNIX_TIMESTAMP())
                  OR (p.start_date < UNIX_TIMESTAMP()
                  AND p.end_date > UNIX_TIMESTAMP() - $psemrange))";

        // Get the actual data.
        $semesters = $DB->get_records_sql($sql);

        return $semesters;
    }

    public static function get_current_sections($s) {
        global $DB;

        $sql = 'SELECT s.*
            FROM mdl_enrol_oes_periods p
                INNER JOIN mdl_enrol_oes_sections s ON p.academic_period_id = s.academic_period_id
            WHERE p.start_date < UNIX_TIMESTAMP(NOW())
                AND p.end_date > UNIX_TIMESTAMP(NOW())
                AND p.enabled = 1';

        $sections = $DB->get_records_sql($sql);
        return $sections;
    }

    public static function get_psd_enrollments($s, $psd = null, $fdate = null) {
        // Set the endpoint.
        $endpoint = 'registrations';

        // Set up the paramaters array.
        $parms = array();

        // Set some more parms up.
        if (!is_null($fdate)) {
            $parms['Last_Updated'] = $fdate;
        }

        if (isset($psd->course_section_definition_id)) {
            $parms['Course_Section_Definition_ID'] = $psd->course_section_definition_id;
            $parms['Academic_Period!Academic_Period_ID'] = $psd->academic_period_id;
        } else if (isset($psd->course_subject_abbreviation)) {
            $parms['Subject_Code'] = $psd->course_subject_abbreviation;
            $parms['Academic_Period!Academic_Period_ID'] = $psd->academic_period_id;
        } else {
            $parms['Academic_Period!Academic_Period_ID'] = $psd->academic_period_id;
        }

        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $sep = self::buildout_settings($s, $endpoint, $parms);

        // Get the sections.
        $enrollments = self::get_data($sep);

        return $enrollments;
    }

    public static function get_programs($s) {
        // Set the endpoint.
        $endpoint = 'programs';

        // Set some more parms up.
        // TODO: Add me back! $parms['Institution!Academic_Unit_ID'] = $s->campus;
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $s = self::buildout_settings($s, $endpoint, $parms);

        // Get the sections.
        $programs = self::get_data($s);

        return $programs;
    }

    public static function clear_insert_programs($programs) {
        global $DB;

        // Build some sql to truncate the table.
        $sql = 'TRUNCATE {enrol_oes_programs}';
        mtrace("  Truncating enrol_oes_programs.");

        // Actually do it and store if we're successful or not.
        $success = $DB->execute($sql);

        // Build the $pgms array for future use.
        $pgms = array();

        // If we successfully truncated, insert data.
        if ($success) {
            mtrace("  Successfully truncated enrol_oes_programs.");

            // Get the program data.
            foreach ($programs as $program) {
                $pgms[] = self::insert_program($program);
            }

        } else {
            mtrace("  Failed to truncate enrol_oes_programs.");
            return $success;
        }

        return $pgms;
    }

    public static function insert_update_programs($programs) {
        // Build the $pgms array for future use.
        $pgms = array();

        // Get the program data.
        foreach ($programs as $program) {

            // Check to see if a program already exists.
            $pgm = self::fetch_program($program);

            // If we have an existing program, update it, if not create one.
            if (isset($pgm->id)) {
                $pgms[] = self::update_program($program, $pgm);
            } else {
                $pgms[] = self::insert_program($program);
            }
        }

        return $pgms;
    }

    public static function update_program($program, $pgm) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_programs';

        // Build the two objects to compare.
        $pgm1 = clone($pgm);

        // Populate the cloned object.
        $pgm1->academic_unit_id = $program->Academic_Unit_ID;
        $pgm1->program_of_study_code = $program->Program_of_Study_Code;
        $pgm1->program_of_study = $program->Program_of_Study;

        // If the objects match.
        if (get_object_vars($pgm) === get_object_vars($pgm1)) {
            self::dtrace("  - Program object match, no update necessary.");

            // Return the original program.
            return $pgm;
        } else {
            self::dtrace("  - $pgm1->program_of_study_code mismatch, update necessary.");

            // Update the record.
            $success = $DB->update_record($table, $pgm1, false);

            // Return the new record.
            return $pgm1;
        }
    }

    public static function fetch_program($program) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_programs';

        // Set the parameters.
        $parms = array('academic_unit_id' => $program->Academic_Unit_ID,
                       'program_of_study_code' => $program->Program_of_Study_Code);

        // Get the program record.
        $pgm = $DB->get_record($table, $parms);

        return $pgm;
    }

    public static function insert_program($program) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_programs';

        // Set the singular data object.
        $dataobj = array(
            'academic_unit_id' => $program->Academic_Unit_ID,
            'program_of_study_code' => $program->Program_of_Study_Code,
            'program_of_study' => $program->Program_of_Study
        );

        // Insert the data.
        $gsid = $DB->insert_record($table, $dataobj, false);

        // We may not need to fetch/send this. Revisit.
        $gs = $DB->get_record($table, array('id' => $gsid));

        return $gs;
    }

    public static function get_grading_schemes($s) {
        // Set the endpoint.
        $endpoint = 'grading_schemes';

        // Set some more parms up.
        $parms['Institution!Academic_Unit_ID'] = $s->campus;
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $s = self::buildout_settings($s, $endpoint, $parms);

        // Get the sections.
        $gradingschemes = self::get_data($s);

        return $gradingschemes;
    }

    public static function clear_insert_grading_schemes($gradingschemes) {
        global $DB;

        $s = self::get_settings();

        // Build some sql to truncate the table.
        $sql = 'TRUNCATE {enrol_oes_grade_schemes}';
        mtrace("  Truncating enrol_oes_grade_schemes.");

        // Actually do it and store if we're successful or not.
        $success = $DB->execute($sql);

        // Build the $gs array for future use.
        $gs = array();

        // If we successfully truncated, insert data.
        if ($success) {
            mtrace("  Successfully truncated enrol_oes_grade_schemes.");

            // Get the grading schemas.
            foreach ($gradingschemes as $gradingschema) {

                // Get the grading schemes from each Grades_group.
                foreach ($gradingschema->Grades_group as $gradingscheme) {

                    // If we have a 2 digit display grade, please use it.
                    if (isset($gradingscheme->Student_Grade_Display_2dig)) {

                          // Set Student_Grade_Display to the max 2 digit value as provided by WorkDay.
                          $gradingscheme->Student_Grade_Display = $gradingscheme->Student_Grade_Display_2dig;
                    }

                    $gsid = isset($gradingschema->Student_Grading_Scheme_ID)
                        ? $gradingschema->Student_Grading_Scheme_ID
                        : $s->campusname . ' Standard Grading Scheme';

                    // Add the grading scheme id into the child array.
                    $gradingscheme->Student_Grading_Scheme_ID = $gsid;

                    // Insert each grading scheme and add it to the $gs array.
                    $gs = array_merge($gs, self::insert_grading_scheme($gradingscheme));
                }
            }

        } else {
            mtrace("  Failed to truncate enrol_oes_grade_schemes.");
            return $success;
        }

        return $gs;
    }

    public static function insert_grading_scheme($gradingscheme) {
        global $DB;

        $s = self::get_settings();
        $gsids = isset($gradingscheme->Student_Grading_Scheme_ID)
            ? $gradingscheme->Student_Grading_Scheme_ID
            : $s->campusname . ' Standard Grading Scheme';

        // Set the table.
        $table = 'enrol_oes_grade_schemes';

// TODO: Do we really need to explode and do this crap?

        // Set the singular data object.
        $dataobj = array(
            'grading_scheme_id' => $gsids,
            'grade_id' => $gradingscheme->Student_Grade_ID,
            'grade_display' => $gradingscheme->Student_Grade_Display,
            'requires_last_attendance' => $gradingscheme->Requires_Last_Attendance,
            'grade_note_required' => $gradingscheme->Grade_Note_Required
        );

        $gs = array();
        $gsa = array();

        // We do not have multiple grading basis, insert the singular item.
        if (!strpos($gradingscheme->Grading_Basis, ';')) {
            $dataobj['grading_basis'] = $gradingscheme->Grading_Basis;

            // Insert the data.
            $gsid = $DB->insert_record($table, $dataobj, true);

            // We may not need to fetch/send this. Revisit.
            $gs[] = $DB->get_record($table, array('id' => $gsid));

        // We have multiple grading basis', go nuts.
        } else {

            // Get the multiple gradeing basis' from the ; separated list.
            $mgb = array_map('trim', explode(';', $gradingscheme->Grading_Basis));

            // Loop through our grading basis'.
            foreach ($mgb as $gb) {

                // Set the data object grading basis accordingly.
                $dataobj['grading_basis'] = $gb;

                // Insert the data.
                $gsid = $DB->insert_record($table, $dataobj, true);

                // We may not need to fetch/send this. Revisit.
                $gsa[] = $DB->get_record($table, array('id' => $gsid));
                $gs = array_merge($gs, $gsa);
            }
        }

        return $gs;
    }

    public static function get_sections($s, $parms) {
        // Set the endpoint.
        $endpoint = 'sections';

        // Set some more parms up.
        if (isset($s->campus)) {
            $parms['Institution!Academic_Unit_ID'] = $s->campus;
        }
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $s = self::buildout_settings($s, $endpoint, $parms);

        // Get the sections.
        $sections = self::get_data($s);

        return $sections;
    }

    public static function get_wd_courses($s) {
    // Set the endpoint.
    $endpoint = 'courses';

        // Set some aprms up.
        $parms = array();
        if (isset($s->campus)) {
            $parms['Institution!Academic_Unit_ID'] = $s->campus;
        }
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $s = self::buildout_settings($s, $endpoint, $parms);

        // Get the units.
        $courses = self::get_data($s);

        return $courses;
    }

    public static function sort_courses($courses) {
        usort($courses, function($a, $b) {
            // First comparison based on subject.
            $coursecomparison = strcmp($a->Course_Subject_Abbreviation, $b->Course_Subject_Abbreviation);
    
            // If 'name' values are equal, compare based on 'count'
            if ($coursecomparison == 0) {
                return strcmp($a->Course_Number, $b->Course_Number);
            }
    
            return $coursecomparison;
        });
        return $courses;
    }

    public static function format_date($longago) {
        $date = date("Y-m-d\TH:i:s", strtotime("$longago"));
        return $date;
    }

    public static function format_pg_date($pgdate) {
        // Parse the date string as a DateTime object.
        $datewithprovidedoffset = new DateTime($pgdate);

        // Specify the expected (local) timezone.
        $expectedtimezone = new DateTimeZone(date_default_timezone_get());

        // Get the offset from the provided date in hours.
        $providedoffset = $datewithprovidedoffset->getOffset() / 3600;

        // Create a DateTime object for the same date in the expected timezone.
        $datewithexpectedtimezone = new DateTime($pgdate);
        $datewithexpectedtimezone->setTimezone($expectedtimezone);

        // Get the offset from the expected date in hours.
        $expectedoffset = $expectedtimezone->getOffset($datewithexpectedtimezone) / 3600;

        // Check if the offsets match.
        if ($providedoffset == $expectedoffset) {
            // Yay! The timzone in WD matches the expected value.
        } else {
            // Find the difference between expected and provided timzones in hours.
            $hourdiff = $expectedoffset - $providedoffset;

            // Update the provided date to migrate from the provided to observed timezone.
            $datewithprovidedoffset->modify(-$hourdiff . ' hours');

            // Reset the DateTime object to use the expected timezone.
            $datewithprovidedoffset->setTimezone($expectedtimezone);
        }

        $formatteddate = $datewithprovidedoffset->format("Y-m-d\TH:i:sP");
//        $utcintdate = $datewithprovidedoffset->getTimestamp();
        $pgdate = $formatteddate;

        // Output the final adjusted date in the correct timezone.
        return $pgdate;
    }

    public static function get_local_units($s) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_units';

        // Set up the conditions.
        $conditions = array('academic_unit_subtype'=>'Institution');

        // Fetch the units.
        $units = $DB->get_records($table, $conditions, $sort = '', $fields = '*');

        return $units;
    }

    public static function get_units($s, $date = null) {
        // Set the endpoint.
        $endpoint = 'units';

        // Set some aprms up.
        $parms = array();

        // Check the campus.
        if (isset($s->campus)) {
            $parms['Superior_Unit!Academic_Unit_ID'] = $s->campus;
            $parms['Institution_ID!Academic_Unit_ID'] = $s->campus;
        }

        // Check the date.
        if (isset($date)) {
            $parms['Last_Updated'] = $date;
        }

        // Make sure we have the parm for our format.
        $parms['format'] = 'json';

        // Build out the settins based on settings, endpoint, and parms.
        $s = self::buildout_settings($s, $endpoint, $parms);

        // Get the units.
        $units = self::get_data($s);

        return $units;
    }

    public static function buildout_settings($s, $endpoint, $parms) {
        // Build the urlencoded params.
        $params = http_build_query($parms, '', '%26');

        // Build the entire urlencoded URL.
        $s->url = urlencode($s->wsurl . '/' . $s->$endpoint . '?') . $params;

        return $s;
    }

    public static function get_dates() {
        // Get the current year.
        $currentyear = date('Y');

        // Get the integer value of the current month.
        $currentmonth = intval(date('m'));

        // Set the start date to the 1st of the year.
        $startdate = $currentyear . '-01-01';

        // Set the end date to the last of either this or next year, depending on month.
        if ($currentmonth < 9) {
            $enddate = ($currentyear) . '-12-31';
        } else {
            $enddate = ($currentyear + 1) . '-12-31';
        }

        // Build the return array.
        $dates = array(
            'Start_Date' => $startdate,
            'End_Date' => $enddate
        );

        return $dates;
    }

    public static function delete_studentmeta($stu) {
        global $DB;
        $starttime = microtime(true);

        // Set the deleted table.
        $dtable = 'enrol_oes_students_meta';

        // Set the deleted parms.
        $dparms = array('studentid' => $stu->id);

        // Delete the records for this student.
        $deleted = $DB->delete_records($dtable, $dparms);

        $endtime = microtime(true);
        $elapsedtime = round($endtime - $starttime, 4);
        self::dtrace("  - Cleaning metadata for $stu->universal_id took $elapsedtime seconds.");

        // Return the bool.
        return $deleted;
    }

    public static function insert_studentmeta($s, $stu, $metafield, $metadata, $period) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_students_meta';

        // Build the $data object.
        $data = new stdClass();

        // Fill out the object.
        $data->studentid = $stu->id;
        $data->datatype = $metafield;
        $data->academic_period_id = $period->academic_period_id;
        $data->data = $metadata;

        // Insert the record.
        $inserted = $DB->insert_record($table, $data, false);

        return $inserted;
    }

    public static function insert_all_studentmeta($s, $stu, $student, $period) {
        // Determine what data we're talking about.
        $metafields = explode(',', $s->metafields);
        $sportfield = $s->sportfield;
        $athletecounter = 0;

        self::dtrace("Beginning to process student metadata for $stu->universal_id.");
        foreach ($metafields as $metafield) {
            $metafield = trim($metafield);
            if (isset($student->$metafield)) {
                $metadata = $student->$metafield;
                $updated = self::insert_studentmeta($s, $stu, $metafield, $metadata, $period);
                if ($updated) {
                    self::dtrace("    $student->Universal_Id - $metafield: $metadata updated.");
                } else {
                    self::dtrace("    ERROR: $student->Universal_Id - $metafield: $metadata failed to populate.");
                }
            }
        }

        foreach ($metafields as $metafield) {
            if (isset($student->$sportfield)) {
                $athletecounter++;
                foreach ($student->$sportfield as $team) {
                    // Update and insert sports and codes as needed.
                    $sport = self::create_update_sportcodes($s, $team);
                    $sports[] = $sport->code;
                    $supdated = self::insert_studentmeta($s, $stu, 'Athletic_Team_ID', $sport->code, $period);
                    if ($supdated) {
                        self::dtrace("    $student->Universal_Id - $student->First_Name $student->Last_Name is on team $sport->code: $sport->name.");
                    } else {
                        self::dtrace("    ERROR: $student->Universal_Id - $student->First_Name $student->Last_Name - $sport->code: $sport->name failed to populate.");
                    }
                }
                break;
            }
        }

        self::dtrace("Finished processing of student metadata for $stu->universal_id.");
        return $athletecounter;
    }

    public static function truncate_studentmeta() {
        global $DB;
        $sql = "TRUNCATE {enrol_oes_students_meta}";
        $truncated = $DB->execute($sql);
        return $truncated;
    }

    public static function delete_insert_studentmeta($s, $stu, $student) {
        // Determine what data we're talking about.
        $metafields = explode(',', $s->metafields);
        $sportfield = $s->sportfield;
        $athletecounter = 0;
        $deleted = self::delete_studentmeta($stu);

        if ($deleted) {
            self::dtrace("  - Beginning processing of student metadata for $stu->universal_id.");
            foreach ($metafields as $metafield) {
                $metafield = trim($metafield);
                if (isset($student->$metafield)) {
                    $metadata = $student->$metafield;
                    self::dtrace("    $student->Universal_Id - $metafield: $metadata");
                    $updated = self::insert_studentmeta($s, $stu, $metafield, $metadata);
                }
            }

            foreach ($metafields as $metafield) {
                if (isset($student->$sportfield)) {
                    $athletecounter++;
                    foreach ($student->$sportfield as $team) {

                        // Update and insert sports and codes as needed.
                        $sport = self::create_update_sportcodes($s, $team);

                        $sports[] = $sport->code;
                        self::dtrace("    $student->Universal_Id - $student->First_Name $student->Last_Name is on team $sport->code: $sport->name.");
                        $supdated= self::insert_studentmeta($s, $stu, 'Athletic_Team_ID', $sport->code);
                    }
                    break;
                }
            }
            self::dtrace("Finished processing of student metadata for $stu->universal_id.");
        } else {
            mtrace("Could not delete student metadata for $student->Universal_ID.");
        }
    }

    public static function check_sportcodes($team) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_sport';

        // Set the parms.
        $parms = array('code' => $team->Athletic_Team_ID);

        // Get tthe data.
        $sport = $DB->get_record($table, $parms);

        return $sport;
    }

    public static function create_sportcode($s, $team) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_sport';

        // Build the new obj.
        $sport = new stdClass();

        // Populate the obj.
        $sport->code = $team->Athletic_Team_ID;
        $sport->name = $team->Athletic_Team;

        // Insert the data.
        $created = $DB->insert_record($table, $sport, $returnid = true);

        if ($created) {
            self::dtrace("  - Inserted sport code: $sport->code with name: $sport->name at id: $created.");
            $sport->id = $created;
            return $sport;
        } else {
            self::dtrace("  - Failed to insert sport code: $sport->code with name: $sport->name.");
            return false;
        }


    }

    public static function update_sportcode($s, $sport, $team) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_sport';

        // Make sure we're only updating mismatches.
        if ($sport->code == $team->Athletic_Team_ID
            && $sport->name == $team->Athletic_Team) {

            // Log we did not update anything.
            self::dtrace("  - Sport code matches team name, skipping.");

            return $sport;
        } else {
            // Set the new sport name.
            $sport->name = $team->Athletic_Team;

            // Update the table.
            $updated = $DB->update_record($table, $sport, false);

            if ($updated) {
                self::dtrace("  - Updated $sport->code name to $sport->name.");
            } else {
                self::dtrace("  - Failed to update $sport->code name to $sport->name.");
            }

            return $sport;
        }
    }

    public static function create_update_sportcodes($s, $team) {
        // Check if the sport code exists.
        $sport = self::check_sportcodes($team);

        if (isset($sport->id)) {
            // Update the sport.
            $updated = self::update_sportcode($s, $sport, $team);
            return $updated;
        } else {
            // Create the student.
            $created = self::create_sportcode($s, $team);
            return $created;
        }
    }

    /**
     * Gets the data from the webservice endpoint.
     *
     * @param  @object $s
     *
     * @return @array of @objects
     */
    public static function get_data($s) {
        global $CFG;

        // Deal with some unfortunate stuff.
        $s->url = urldecode(urldecode($s->url));

        // Initiate the curl.
        $ch = curl_init($s->url);

        // Set the curl options.
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$s->username:$s->password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        // Debug this connection.
        if ($CFG->debugdisplay == 1) {
// TODO: readd            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        // Get the data.
        $json_response = curl_exec($ch);

        // Get the http code for later.
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check if the cURL request was successful.
        if(curl_errno($ch)) {
            $curlerror = curl_error($ch);

            // Return the error.
            mtrace("cURL ERROR: $curlerror. Aborting.");
            return "error";

        // Check to see that we have a proper response.
        } else if ($httpcode != "200") {

            // Return the HTTP status code.
            mtrace("SERVER ERROR - HTTP Status Code: $httpcode. Aborting.");
            return "error";
        }

        // Close the curl.
        curl_close($ch);

        // Decode the json.
        $dataobj = json_decode($json_response);

        // Get the data we need.
        if (isset($dataobj->Report_Entry)) {
            $datas = $dataobj->Report_Entry;
        } else {
            $datas = null;
        }

        // Return the data.
        return $datas;
    }

    public static function get_data_raw($s) {
        // Deal with some unfortunate stuff.
        $s->url = urldecode(urldecode($s->url));

        // Initiate the curl.
        $ch = curl_init($s->url);

        // Set the curl options.
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$s->username:$s->password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        // Get the data.
        $json_response = curl_exec($ch);

        // Get the http code for later.
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check if the cURL request was successful.
        if(curl_errno($ch)) {
            $curlerror = curl_error($ch);

            // Return the error.
            mtrace("cURL ERROR: $curlerror. Aborting.");
            return "error";

        // Check to see that we have a proper response.
        } else if ($httpcode != "200") {

            // Return the HTTP status code.
            mtrace("SERVER ERROR - HTTP Status Code: $httpcode. Aborting.");
            return "error";
        }

        // Close the curl.
        curl_close($ch);

        // Decode the json.
        $dataobj = json_decode($json_response);

        // Return the data.
        return $dataobj;
    }

    public static function get_suffix_from_institution($student) {

        // Check if the 'Institution' key exists in the object.
        if (isset($student->Institution)) {

            // Use regular expression to match the first sequence of non-space characters.
            if (preg_match('/^\S+/', $student->Institution, $matches)) {

                // Return the data I am looking for.
                return $matches[0];
            }
        }

        // Otherwise return null.
        return null;
    }

    public static function get_email_or_idnumber($s, $student, $type) {
        // Build out the required data.
        $suffix = $s->campusname . '_' . $type;
        if (isset($student->$suffix)) {
            $data = isset($student->$suffix) ? $student->$suffix : null;
        } else {
            self::dtrace("We found a non-default user, deal with it.");
            $suffix = self::get_suffix_from_institution($student) . '_' . $type;
            $data = isset($student->$suffix) ? $student->$suffix : null;
        }
        return $data;
    }

    public static function check_istudent($s, $student) {
        global $DB;

        // Get the student email from the object.
        $email = self::get_email_or_idnumber($s, $student, 'Email');

        // Get the student legacy ID from the object.
        $lid = self::get_email_or_idnumber($s, $student, 'Legacy_ID');

        // Set up the SQL to look for the student in the LMS.
        $sql = 'SELECT *
                FROM {enrol_oes_students} stu
                WHERE stu.universal_id = "' . $student->Universal_Id . '"
                OR stu.email = "' . $email . '"';

        $stus = $DB->get_records_sql($sql);
        if (count($stus) > 1) {
            foreach ($stus as $stu) {
                $schoolid = !is_null($stu->school_id) ?
                    ' ' . $stu->school_id . ',' :
                    "";
                mtrace('DB ID: ' . $stu->id . ', ' .
                    $stu->universal_id . ', ' .
                    $stu->email . ',' .
                    $stu->school_id . ' - ' .
                    $stu->firstname . ' ' .
                    $stu->lastname . ' is a dupe of remote user ' . 
                    $student->Universal_Id . ', ' .
                    $email . ', ' .
                    $lid . ', ' .
                    $student->First_Name . ' ' .
                    $student->Last_Name . '.');
            }

            // Do not process this student.
            $stu = null;

        } else if (count($stus) == 1) {
            // Grab the student from the array. 
            self::dtrace("  Student found with universal_id: $student->Universal_Id, email: $email, school_id: $lid.");
            $stu = reset($stus);

        } else {
            self::dtrace("  No student with universal_id: $student->Universal_Id, email: $email, school_id: $lid.");
            $stu = null;
        }

        return $stu;
    }

    public static function update_istudent($s, $stu, $student) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_students';

        $esuffix = $s->campusname . '_Email';
        if (isset($student->$esuffix)) {
            $email = $student->$esuffix;
        } else {
            self::dtrace("\nWe found a non-default user, deal with it.");
            $esuffix = self::get_suffix_from_institution($student) . '_Email';
            $email = $student->$esuffix;
        }

        // Build out the legacy idnumber query.
        $lidsuffix = $s->campusname . '_Legacy_ID';
        if (isset($student->$lidsuffix)) {
            $lid = $student->$lidsuffix;
        } else {
            $lidsuffix = self::get_suffix_from_institution($student) . '_Legacy_ID';
            $lid = isset($student->$lidsuffix) ? $student->$lidsuffix : null;
        }

        // Build the two objects to compare.
        $stu1 = clone($stu);
        $stu2 = new stdClass();

        // Un/populate the two objects.
        unset($stu1->id);
        unset($stu1->username);
        unset($stu1->userid);
        unset($stu1->lastupdate);
        $stu2->universal_id = $student->Universal_Id;
        $stu2->email = $email;
        $stu2->school_id = $lid;
        $stu2->firstname = $student->First_Name;
        $stu2->preferred_firstname = isset($student->Preferred_First_Name) ? $student->Preferred_First_Name : null;
        $stu2->lastname = $student->Last_Name;
        $stu2->preferred_lastname = isset($student->Preferred_Last_Name) ? $student->Preferred_Last_Name : null;
        $stu2->middlename = isset($student->Middle_Name) ? $student->Middle_Name : null;

        // If the objects match.
        if (get_object_vars($stu1) === get_object_vars($stu2)) {
            self::dtrace("  - Student objects match, no update necessary.");

            // Return the original student.
            return $stu;
        } else {
            // Set the id.
            $stu2->id = $stu->id;

            // Set the datestamp.
            $stu2->lastupdate = time();

            // Update the record.
            $istudent = $DB->update_record($table, $stu2, false);

            self::dtrace("  - Updated student with universal_id: $stu->universal_id - $student->Universal_Id, email: $stu->email - $email, school_id: $stu->school_id - $lid.");

            return $istudent;
        }
    }

    public static function create_istudent($s, $student) {
        global $DB;

        // Build out the email query.
        $esuffix = $s->campusname . '_Email';
        $email = $student->$esuffix;

        // Build out the legacy idnumber query.
        $lidsuffix = $s->campusname . '_Legacy_ID';
        $lid = isset($student->$lidsuffix) ? $student->$lidsuffix : null;

        // Set the table;
        $table = 'enrol_oes_students';

        // Build the object.
        $data = new stdClass();
        $data->universal_id = $student->Universal_Id;
        $data->email = $email;
        $data->username = $email;
        $data->school_id = $lid;
        $data->userid = null;
        $data->firstname = $student->First_Name;
        $data->preferred_firstname = isset($student->Preferred_First_Name) ? $student->Preferred_First_Name : null;
        $data->lastname = $student->Last_Name;
        $data->preferred_lastname = isset($student->Preferred_Last_Name) ? $student->Preferred_Last_Name : null;
        $data->middlename = isset($student->Middle_Name) ? $student->Middle_Name : null;
        $data->lastupdate = time();

        $success = $DB->insert_record($table, $data, true);

        if (is_int($success)) {
            $stu = $DB->get_record($table, array('id' => $success));
            self::dtrace("  - Created student with universal_id: $stu->universal_id, email: $stu->email, school_id: $stu->school_id.");
            return $stu;
        } else {
            mtrace("  - Failed to create interstitial student.");
            var_dump($student);
            return false;
        }
    }

    public static function create_update_istudent($s, $student) {
        $starttime = microtime(true);

        // Check if the student exists.
        $stu = self::check_istudent($s, $student);

        if (isset($stu->id)) {
            // Update the student.
            $updated = self::update_istudent($s, $stu, $student);

            $endtime = microtime(true);
            $elapsedtime = round($endtime - $starttime, 4);
            self::dtrace("  Student: $stu->universal_id took $elapsedtime seconds to process.");

            return $stu;

        } else {
            // Create the student.
            $stu = self::create_istudent($s, $student);

            $endtime = microtime(true);
            $elapsedtime = round($endtime - $starttime, 4);
            self::dtrace("  Student: $stu->universal_id took $elapsedtime seconds to process.");

            return $stu;
        }
    }

    public static function create_update_iteacher($s, $teacher) {
        $starttime = microtime(true);
        
        // Check if the teacher exists.
        $tea = self::check_iteacher($s, $teacher);

        if (isset($tea->id)) {
            // Update the teacher.
            $updated = self::update_iteacher($s, $tea, $teacher);

            $endtime = microtime(true);
            $elapsedtime = round($endtime - $starttime, 4);
            self::dtrace(" User: $tea->universal_id took $elapsedtime seconds to process.");

            return $tea;

        } else {
            // Create the teacher.
            $tea = self::create_iteacher($s, $teacher);

            if (!$tea) {
                return false;
            }

            $endtime = microtime(true);
            $elapsedtime = round($endtime - $starttime, 4);
            self::dtrace(" User: $tea->universal_id took $elapsedtime seconds to process.");

            return $tea;
        }
    }

    public static function check_iteacher($s, $teacher) {
        global $DB;

        if (!isset($teacher->Instructor_Email) || !isset($teacher->Instructor_ID)) {
            self::dtrace("Instructor Email / ID not found, skipping.");
            var_dump($teacher);
            return false;
        }

        $sql = 'SELECT *
                FROM {enrol_oes_teachers} tea
                WHERE tea.universal_id = "' . $teacher->Instructor_ID . '"
                OR tea.email = "' . $teacher->Instructor_Email . '"';

        $teas = $DB->get_records_sql($sql);
        if (count($teas) > 1) {
            foreach ($teas as $tea) {
                mtrace('DB ID: ' . $tea->id . ', ' .
                    $tea->universal_id . ', ' .
                    $tea->email . ',' .
                    $tea->firstname . ' ' .
                    $tea->lastname . ' is a dupe of remote user ' .
                    $teacher->Instructor_ID . ', ' .
                    $teacher->Instructor_Email . ', ' .
                    $teacher->Instructor_First_Name . ' ' .
                    $teacher->Instructor_Last_Name . '.');
            }

            // Do not process this teacher.
            $tea = null;

        } else if (count($teas) == 1) {
            // Grab the teacher from the array.
            self::dtrace(" User found with universal_id: $teacher->Instructor_ID, email: $teacher->Instructor_Email.");
            $tea = reset($teas);

        } else {
            self::dtrace(" No user with universal_id: $teacher->Instructor_ID, email: $teacher->Instructor_Email.");
            $tea = null;
        }

        return $tea;
    }

    public static function update_iteacher($s, $tea, $teacher) {
        global $DB;

        // Set the table.
        $table = 'enrol_oes_teachers';

        // Build out the email.
        $email = $teacher->Instructor_Email;

        // Build the two objects to compare.
        $tea1 = clone($tea);
        $tea2 = new stdClass();

        // Un/populate the two objects.
        unset($tea1->id);
        unset($tea1->username);
        unset($tea1->userid);
        unset($tea1->school_id);
        unset($tea1->preferred_firstname);
        unset($tea1->preferred_lastname);
        unset($tea1->middlename);
        unset($tea1->lastupdate);
        $tea2->universal_id = $teacher->Instructor_ID;
        $tea2->email = $email;
        $tea2->firstname = $teacher->Instructor_First_Name;
        $tea2->lastname = $teacher->Instructor_Last_Name;

        // If the objects match.
        if (get_object_vars($tea1) === get_object_vars($tea2)) {
            self::dtrace(" User objects match, no update necessary.");

            // Return the original teacher.
            return false;
        } else {
            // Set the id.
            $tea2->id = $tea->id;

            // Set the datestamp.
            $tea2->lastupdate = time();

            // Update the record.
            $iteacher = $DB->update_record($table, $tea2, false);

            self::dtrace(" Updated user with universal_id: $tea->universal_id - $teacher->Instructor_ID, email: $tea->email - $email.");

            return $iteacher;
        }
    }

    public static function create_iteacher($s, $teacher) {
        global $DB;

        // Build out the email query.
        if (isset($teacher->Instructor_Email)) {
            $email = $teacher->Instructor_Email;
        } else {
            mtrace(" Failed to create interstitial user. No email provided.");
            mtrace($teacher->Instructor_ID);
            return false;
        }

        // Set the table;
        $table = 'enrol_oes_teachers';

        // Build the object.
        $data = new stdClass();
        $data->universal_id = $teacher->Instructor_ID;
        $data->email = $email;
        $data->username = $email;
        $data->userid = null;
        $data->firstname = $teacher->Instructor_First_Name;
        $data->lastname = $teacher->Instructor_Last_Name;
        $data->lastupdate = time();

        $success = $DB->insert_record($table, $data, true);

        if (is_int($success)) {
            $tea = $DB->get_record($table, array('id' => $success));
            self::dtrace(" Created user with universal_id: $tea->universal_id, email: $tea->email.");
            return $tea;
        } else {
            mtrace(" Failed to create interstitial user.");
            var_dump($teacher);
            return false;
        }
    }

    public static function insert_update_teacher_enrollment($sectionid, $universalid, $role, $status) {
        global $DB;
        $table = 'enrol_oes_teacher_enrollments';

//        if (is_null($universalid) && is_null($role)) {

            $usql = 'SELECT * FROM {enrol_oes_teacher_enrollments} e
                    WHERE e.section_listing_id = "' . $sectionid . '"
                        AND (e.status = "enroll" OR e.status = "enrolled")
                        AND (e.role = "teacher" OR e.role = "primary")';

            $uenrs = $DB->get_records_sql($usql);

            $unenrolls = array();
            if (!empty($uenrs)) {
                foreach ($uenrs as $uenr) {
                    $sql = 'UPDATE {enrol_oes_teacher_enrollments} e
                                SET e.status = "unenroll",
                                    e.prevstatus = "' . $uenr->status . '",
                                    e.role = "' . $uenr->role . '",
                                    e.prevrole = "' . $uenr->role . '"
                            WHERE e.section_listing_id = "' . $sectionid . '"
                                AND e.universal_id = "' . $uenr->universal_id . '"
                                AND (e.status = "enroll" OR e.status = "enrolled")
                                AND (e.role = "teacher" OR e.role = "primary")';
                    $unenrolls[] = $DB->execute($sql);
                    self::dtrace("  $uenr->universal_id set to unenroll in $sectionid.");
                }

                self::dtrace(" All enrolled teachers in $sectionid set to unenroll.");
            }
//            return $unenrolls;
//        }

        if (!is_null($universalid) && !is_null($role)) {

            $parm = array('section_listing_id' => $sectionid, 'universal_id' => $universalid);

            $enr = $DB->get_record($table, $parm);

            if (!$enr) {
                $data = new stdClass();

                $data->universal_id = $universalid;
                $data->section_listing_id = $sectionid;
                $data->role = $role;
                $data->status = $status;

                $enroll = $DB->insert_record($table, $data, true);
                self::dtrace(" - Inserted $universalid in $sectionid with role: $data->role and status: $data->status");

                return $enroll;
            } else {
                $data = clone($enr);

                $data->universal_id = $universalid;
                $data->section_listing_id = $sectionid;
                $data->prevrole = $enr->role;
                $data->role = $role;
                $data->status = $status;
                if ($enr->status == 'unenroll' && ($enr->prevstatus == 'enroll' || $enr->prevstatus == 'enrolled')) {
                    $data->prevstatus = $data->prevstatus;
                } else {
                    $data->prevstatus = $enr->status;
                }

                // Compare the objects.
                if (get_object_vars($data) === get_object_vars($enr)) {
                    self::dtrace(" - Enrollment entry: $data->id matches exactly, skipping.");

                    return $enr;
                } else {
                    $enroll = $DB->update_record($table, $data, false);
                    self::dtrace(" - Updated: $data->id - $universalid in $sectionid with role: $data->role and status: $data->status");

                    return $enroll;
                }
            }
        }
    //    self::dtrace(" - User: $universalid present in $sectionid with role: $enr->role and status: $enr->status");
    }

    /**
     * Finds similar objects.
     *
     * @param  @object $obj1
     * @param  @object $obj2
     *
     * @return @float $similarity
     */
    public static function wdstu_compareobjects($obj1, $obj2) {
        // Get the object variables as arrays for each of the objects.
        $properties1 = get_object_vars($obj1);
        $properties2 = get_object_vars($obj2);

        // Set this up for later.
        $countsimilar = 0;

        // Count the total object variables across both objects.
        $counttotal = count($properties1) + count($properties2);

        // Loop through the properties for 1st object and find matching values.
        foreach ($properties1 as $key => $value) {
            // If we find a match, increment the similarity count.
            if (isset($properties2[$key]) && $properties2[$key] == $value) {
                $countsimilar++;
            }
        }

        // Get the similarity percentage.
        $similarity = round(($countsimilar / $counttotal) * 100, 2);

        // Return the similarity percentage.
        return $similarity;
    }

    public static function dtrace($message, $indent = null) {
        global $CFG;
        $indent = !is_null($indent) ? '' : $indent;
        if ($CFG->debugdisplay == 1) {
            $mtrace = mtrace($message);
            return $mtrace;
        } else {
            self::$dtc++;
            if (self::$dtc % 50 === 0) {
                $mtrace = print(".\n");
            } else {
                $mtrace = print(".");
            }

            return $mtrace;
        }
    }

    /**
     * Contructs and sends error emails using Moodle functionality.
     *
     * @package   enrol_workdaystudent
     *
     * @param     @object $emaildata
     * @param     @object $s
     *
     * @return    @bool
     */
    public static function send_wdstu_email($emaildata, $s) {
        global $CFG, $DB;

        // Get email subject from email log.
        $emailsubject = $emaildata->subject;

        // Get email content from email log.
        $emailcontent = $emaildata->body;

        // Grab the list of usernames from Moodle.
        $usernames = explode(",", $s->contacts);

        // Set up the users array.
        $users = array();

        // Loop through the usernames and add each user object to the user array.
        foreach ($usernames as $username) {

            // Make sure we have no spaces.
            $username = trim($username);

            // Add the user object to the array.
            $users[] = $DB->get_record('user', array('username' => $username));
        }

        // Send an email to each of the above users.
        foreach ($users as $user) {

            // Email the message.
            email_to_user($user,
                get_string("workdaystudent_emailname", "enrol_workdaystudent"),
                $emailsubject . " - " . $CFG->wwwroot,
                $emailcontent);
        }
    }
}

class wdscronhelper {

    public static function cronunits() {
        global $CFG;

        // Get settings. We have to do this several times as I overload them.
        $s = workdaystudent::get_settings();

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

        return true;
    }

    public static function cronperiods() {
        global $CFG;

        // Reset the counter. I hate this but I hate the weird logs more.
        workdaystudent::resetdtc();

        // Get settings. We have to do this several times as I overload them.
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

        return true;
    }

}

/*
class enrol_workdaystudent extends enrol_plugin {

    /**
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
/*
    public static function add_enroll_instance($course) {
        return $instance;
    }
}
*/
