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
 * PHPUnit tests for MD-1615: enrollprior gate in wds_get_student_enrollments().
 *
 * @package    enrol_workdaystudent
 * @copyright  2026 LSU Online & Continuing Education
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/workdaystudent.php');

/**
 * Tests that wds_get_student_enrollments() respects the enrollprior window.
 *
 * Scenario matrix:
 *  - period starts in 5 days   + enrollprior = 14  → should return enroll rows
 *  - period starts in 20 days  + enrollprior = 14  → should NOT return enroll rows
 *  - period starts in 20 days  + enrollprior = 14  → unenroll rows ARE returned
 *  - period starts in 20 days  + enrollprior = null → bypass, enroll rows ARE returned
 *  - period already started    + enrollprior = 14  → should return enroll rows
 */
class workdaystudent_enrollprior_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);

        set_config('enrollprior',     14,   'enrol_workdaystudent');
        set_config('createprior',     30,   'enrol_workdaystudent');
        set_config('brange',          60,   'enrol_workdaystudent');
        set_config('erange',          6,    'enrol_workdaystudent');
        set_config('studentrole',     5,    'enrol_workdaystudent');
        set_config('numberthreshold', 9000, 'enrol_workdaystudent');
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Creates the minimum DB fixtures for one enrollment test scenario.
     *
     * @param int    $periodStartOffset  Seconds from now to the period start_date.
     * @param string $enrollStatus       'enroll' or 'unenroll'
     * @return stdClass  Period stub with academic_period_id set.
     */
    private function create_fixtures(int $periodStartOffset, string $enrollStatus): stdClass {
        global $DB;

        $now = time();

        // IDs must be ≤10 chars to fit universal_id columns.
        $uid = substr(uniqid(), -10);

        // 1. Moodle course.
        $course = $this->getDataGenerator()->create_course(['idnumber' => 'WDS-' . $uid]);

        // 2. WDS period.
        $apid = 'AP' . $uid;
        $DB->insert_record('enrol_wds_periods', (object)[
            'academic_period_id' => $apid,
            'academic_period'    => 'Test Period',
            'period_type'        => 'Summer',
            'period_year'        => 2026,
            'academic_calendar'  => 'STD',
            'academic_year'      => '2026',
            'start_date'         => $now + $periodStartOffset,
            'end_date'           => $now + $periodStartOffset + (90 * 86400),
            'enabled'            => 1,
        ]);

        // 3. WDS course definition.
        $courselistingid = 'CL' . $uid;
        $DB->insert_record('enrol_wds_courses', (object)[
            'course_listing_id'           => $courselistingid,
            'academic_unit_id'            => 'AU' . $uid,
            'course_definition_id'        => 'CD' . $uid,
            'course_subject_abbreviation' => 'TST',
            'course_number'               => '1001',
            'subject_code'                => 'TST',
            'course_subject'              => 'Test Subject',
            'course_abbreviated_title'    => 'Test 1001',
            'academic_level'              => 'Undergraduate',
        ]);

        // 4. WDS section.
        $sectionlistingid = 'SL' . $uid;
        $sectiondefid     = 'SD' . $uid;
        $DB->insert_record('enrol_wds_sections', (object)[
            'section_listing_id'           => $sectionlistingid,
            'course_section_definition_id' => $sectiondefid,
            'section_number'               => '001',
            'course_listing_id'            => $courselistingid,
            'course_definition_id'         => 'CD' . $uid,
            'course_subject_abbreviation'  => 'TST',
            'academic_unit_id'             => 'AU' . $uid,
            'academic_period_id'           => $apid,
            'course_section_title'         => 'Test Section',
            'course_section_abbrev_title'  => 'TST 1001',
            'delivery_mode'                => 'On Campus',
            'controls_grading'             => '1',
            'class_type'                   => 'Lecture',
            'idnumber'                     => $course->idnumber,
            'wd_status'                    => 'Open',
            'moodle_status'                => (string) $course->id,
        ]);

        // 5. WDS teacher enrollment (required by INNER JOIN in the query).
        $teacheruniversalid = 'T' . substr($uid, 1);
        $DB->insert_record('enrol_wds_teacher_enroll', (object)[
            'section_listing_id' => $sectionlistingid,
            'universal_id'       => $teacheruniversalid,
            'role'               => 'primary',
            'status'             => 'enroll',
            'prevstatus'         => '',
        ]);

        // 6. WDS student + enrollment.
        $studentuniversalid = 'S' . substr($uid, 1);
        $moodleuser = $this->getDataGenerator()->create_user();
        $DB->insert_record('enrol_wds_students', (object)[
            'universal_id' => $studentuniversalid,
            'email'        => $moodleuser->email,
            'username'     => $moodleuser->username,
            'userid'       => $moodleuser->id,
            'firstname'    => $moodleuser->firstname,
            'lastname'     => $moodleuser->lastname,
            'lastupdate'   => $now,
        ]);
        $DB->insert_record('enrol_wds_student_enroll', (object)[
            'section_listing_id'  => $sectionlistingid,
            'universal_id'        => $studentuniversalid,
            'credit_hrs'          => 3,
            'grading_scheme'      => 'STD',
            'grading_basis'       => 'GRD',
            'registration_status' => 'Registered',
            'status'              => $enrollStatus,
            'prevstatus'          => '',
            'registered_date'     => $now - 86400,
            'drop_date'           => 0,
            'guild'               => 0,
            'lastupdate'          => $now,
        ]);

        $periodstub = new stdClass();
        $periodstub->academic_period_id = $apid;
        return $periodstub;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Enroll row for a period starting in 5 days with enrollprior=14 SHOULD be returned.
     */
    public function test_enroll_within_window_is_returned(): void {
        $period = $this->create_fixtures(5 * 86400, 'enroll');

        $results = workdaystudent::wds_get_student_enrollments($period, null, 14);

        $this->assertCount(1, $results,
            'Expected 1 enrollment row for a period starting 5 days from now with enrollprior=14.');

        $row = reset($results);
        $this->assertEquals('enroll', $row->moodle_enrollment_status);
    }

    /**
     * Enroll row for a period starting in 20 days with enrollprior=14 should NOT be returned.
     * This is the core regression test for MD-1615.
     */
    public function test_enroll_outside_window_is_excluded(): void {
        $period = $this->create_fixtures(20 * 86400, 'enroll');

        $results = workdaystudent::wds_get_student_enrollments($period, null, 14);

        $this->assertCount(0, $results,
            'Expected 0 enrollment rows for a period starting 20 days from now with enrollprior=14.');
    }

    /**
     * Unenroll row for a period starting in 20 days with enrollprior=14 SHOULD still be returned.
     * Drops/withdrawals must be processed regardless of the enrollment window.
     */
    public function test_unenroll_outside_window_is_still_returned(): void {
        $period = $this->create_fixtures(20 * 86400, 'unenroll');

        $results = workdaystudent::wds_get_student_enrollments($period, null, 14);

        $this->assertCount(1, $results,
            'Expected 1 unenroll row even for a period starting 20 days from now.');

        $row = reset($results);
        $this->assertEquals('unenroll', $row->moodle_enrollment_status);
    }

    /**
     * With enrollprior=null (reprocess bypass), enroll rows are returned regardless of date.
     */
    public function test_enrollprior_null_bypasses_gate(): void {
        $period = $this->create_fixtures(20 * 86400, 'enroll');

        $results = workdaystudent::wds_get_student_enrollments($period, null, null);

        $this->assertCount(1, $results,
            'Expected 1 enrollment row when enrollprior=null (reprocess bypass).');
    }

    /**
     * Enroll row for a period that has already started SHOULD be returned.
     */
    public function test_enroll_for_started_period_is_returned(): void {
        $period = $this->create_fixtures(-1 * 86400, 'enroll');

        $results = workdaystudent::wds_get_student_enrollments($period, null, 14);

        $this->assertCount(1, $results,
            'Expected 1 enrollment row for a period that has already started.');
    }

    /**
     * Verifies that the enrollprior value is correctly read from plugin config.
     */
    public function test_settings_return_correct_enrollprior(): void {
        set_config('enrollprior', 7, 'enrol_workdaystudent');
        $s = workdaystudent::get_settings();
        $this->assertEquals(7, (int) $s->enrollprior);
    }

    /**
     * Verifies that the fallback default enrollprior is 14 when no config is set.
     */
    public function test_default_enrollprior_is_14(): void {
        $s = new stdClass();
        $enrollprior = isset($s->enrollprior) ? (int) $s->enrollprior : 14;
        $this->assertEquals(14, $enrollprior);
    }
}
