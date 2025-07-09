<?php

require_once(__DIR__ . '/../../config.php'); // Adjust path as necessary if plugin structure is different

require_login();
require_capability('moodle/site:config', context_system::instance()); // Or a more specific capability

global $CFG, $DB, $OUTPUT, $PAGE;

$courseidfilter = optional_param('courseid', '', PARAM_RAW); // Use PARAM_RAW for now, will be used as is for DB compare

$PAGE->set_url('/enrol/workdaystudent/teacher_enroll_admin.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('teacher_enroll_admin_title', 'enrol_workdaystudent'));

$action = optional_param('action', '', PARAM_ALPHA);
$teacherenrollid_param = optional_param('teacherenrollid', 0, PARAM_INT);
$sectionlistingid_param = optional_param('sectionlistingid', '', PARAM_RAW); 

// Store courseidfilter from GET/POST to ensure it's available after POST operations like delete confirmation
if ($courseidfilter === '' && $action !== '') { // If action is set, it might be a POST from confirmation
    $courseidfilter = optional_param('courseidfilter_hidden', '', PARAM_RAW);
}


if ($action === 'delete_confirm' && $teacherenrollid_param > 0 && !empty($sectionlistingid_param)) {
    require_sesskey(); // Check sesskey from the GET link

    echo $OUTPUT->header(); // Output header early for confirmation page
    echo $OUTPUT->heading(get_string('confirmdeletion', 'enrol_workdaystudent'));

    // Fetch details for confirmation message
    $enrollment_details_sql = "SELECT t.firstname, t.lastname, te.section_listing_id
                                 FROM {enrol_wds_teacher_enroll} te
                                 JOIN {enrol_wds_teachers} t ON te.universal_id = t.universal_id
                                WHERE te.id = :teacherenrollid";
    $enrollment_details = $DB->get_record_sql($enrollment_details_sql, ['teacherenrollid' => $teacherenrollid_param]);

    if ($enrollment_details) {
        $message = get_string('confirm_delete_teacher_enroll_detail', 'enrol_workdaystudent', [
            'teachername' => fullname($enrollment_details),
            'sectionid' => $enrollment_details->section_listing_id
        ]);
        echo $OUTPUT->box_start('generalbox boxwidthnarrow');
        echo '<p>' . $message . '</p>';
        
        $form_url = new moodle_url('/enrol/workdaystudent/teacher_enroll_admin.php');
        echo '<form method="post" action="' . $form_url . '">';
        echo '<input type="hidden" name="action" value="delete_execute">';
        echo '<input type="hidden" name="teacherenrollid" value="' . $teacherenrollid_param . '">';
        echo '<input type="hidden" name="sectionlistingid" value="' . $sectionlistingid_param . '">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">'; // New sesskey for the POST form
        echo '<input type="hidden" name="courseidfilter_hidden" value="' . htmlspecialchars($courseidfilter) . '">'; // Pass along original search
        
        echo '<button type="submit" class="btn btn-danger">' . get_string('yesdelete', 'enrol_workdaystudent') . '</button> ';
        
        $cancel_url = new moodle_url('/enrol/workdaystudent/teacher_enroll_admin.php', ['courseid' => $courseidfilter]);
        echo html_writer::link($cancel_url, get_string('cancel', 'core'), ['class' => 'btn btn-secondary']);
        
        echo '</form>';
        echo $OUTPUT->box_end();
    } else {
        echo $OUTPUT->notification(get_string('error'), 'error'); // Generic error
    }

    echo $OUTPUT->footer();
    exit; // Stop further processing for confirmation page

} else if ($action === 'delete_execute' && $teacherenrollid_param > 0 && !empty($sectionlistingid_param)) {
    require_sesskey(); // Check sesskey from the POST form

    $transaction = $DB->start_delegated_transaction();
    $success = true;

    // Operation 1: Delete the teacher enrollment entry
    if (!$DB->delete_records('enrol_wds_teacher_enroll', ['id' => $teacherenrollid_param])) {
        $success = false;
    }

    // Operation 2: Update student statuses
    if ($success) {
        $update_student_sql = "UPDATE {enrol_wds_student_enroll}
                                  SET status = :status
                                WHERE section_listing_id = :sectionlistingid";
        $update_student_params = [
            'status' => 'ToBeUpdated',
            'sectionlistingid' => $sectionlistingid_param
        ];
        if (!$DB->execute($update_student_sql, $update_student_params)) {
            // Note: $DB->execute can return true on success or throw an exception on failure.
            // Depending on DB driver and Moodle version, checking return might not be enough.
            // For simplicity, we assume it works or an exception would halt.
            // A more robust check might involve $DB->count_records_sql for affected rows if necessary.
        }
    }

    if ($success) {
        $transaction->commit();
        \core\notification::success(get_string('teacher_enroll_deleted', 'enrol_workdaystudent'));
    } else {
        $transaction->rollback();
        \core\notification::error(get_string('teacher_enroll_delete_error', 'enrol_workdaystudent'));
    }
    // Redirect back to the page, retaining the courseid filter if it was set
    // courseidfilter is now retrieved from courseidfilter_hidden if it was a POST
    $redirect_url = new moodle_url('/enrol/workdaystudent/teacher_enroll_admin.php');
    if (!empty($courseidfilter)) { // Use the potentially restored courseidfilter
        $redirect_url->param('courseid', $courseidfilter);
    }
    redirect($redirect_url);
}


// If not showing a confirmation page, proceed with normal page display
$PAGE->set_heading(get_string('teacher_enroll_admin_heading', 'enrol_workdaystudent'));
$PAGE->navbar->add(get_string('pluginname', 'enrol_workdaystudent'));
$PAGE->navbar->add(get_string('teacher_enroll_admin_nav', 'enrol_workdaystudent'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teacher_enroll_admin_heading', 'enrol_workdaystudent'));

// Search Form
echo '<form method="post" action="teacher_enroll_admin.php">';
echo '<div class="mb-3">';
echo '<label for="courseid">' . get_string('courseidlabel', 'enrol_workdaystudent') . '</label>';
echo '<input type="text" name="courseid" id="courseid" value="' . htmlspecialchars($courseidfilter) . '" class="form-control">';
echo '</div>';
echo '<button type="submit" class="btn btn-primary">' . get_string('search', 'core') . '</button>';
echo '</form>';

// Results will be displayed here
if ($courseidfilter !== '') {
    // Query 1: Find section_listing_ids based on the moodle_status (which user confirmed is course.id)
    $sql_sections = "SELECT section_listing_id
                       FROM {enrol_wds_sections}
                      WHERE moodle_status = :courseid";
    $section_params = ['courseid' => $courseidfilter];
    $section_listing_ids = $DB->get_fieldset_sql($sql_sections, $section_params);

    if (!empty($section_listing_ids)) {
        list($in_sql, $in_params) = $DB->get_in_or_equal($section_listing_ids, SQL_PARAMS_NAMED, 'sli');

        // Query 2: Find Teacher Enrollments
        $sql_teachers = "SELECT te.id AS teacherenrollid, te.universal_id AS teacheruniversalid, te.section_listing_id,
                                t.firstname, t.lastname, t.email
                           FROM {enrol_wds_teacher_enroll} te
                           JOIN {enrol_wds_teachers} t ON te.universal_id = t.universal_id
                          WHERE te.section_listing_id " . $in_sql;
        
        $teacher_enrollments = $DB->get_records_sql($sql_teachers, $in_params);

        if ($teacher_enrollments) {
            $table = new html_table();
            $table->head = [
                get_string('teacheruniversalid', 'enrol_workdaystudent'),
                get_string('teachername', 'enrol_workdaystudent'),
                get_string('teacheremail', 'enrol_workdaystudent'),
                get_string('sectionlistingid', 'enrol_workdaystudent'),
                get_string('action', 'enrol_workdaystudent')
            ];
            $table->data = [];

            foreach ($teacher_enrollments as $enrollment) {
                $confirm_delete_url = new moodle_url('/enrol/workdaystudent/teacher_enroll_admin.php', [
                    'action' => 'delete_confirm',
                    'teacherenrollid' => $enrollment->teacherenrollid,
                    'sectionlistingid' => $enrollment->section_listing_id,
                    'sesskey' => sesskey(), // Pass sesskey for the first step of confirmation
                    'courseid' => $courseidfilter 
                ]);
                // Removed JavaScript confirm, link now goes to a confirmation page
                $delete_button = $OUTPUT->action_link($confirm_delete_url, get_string('delete', 'core'));
                
                $table->data[] = [
                    htmlspecialchars($enrollment->teacheruniversalid),
                    htmlspecialchars($enrollment->firstname . ' ' . $enrollment->lastname),
                    htmlspecialchars($enrollment->email),
                    htmlspecialchars($enrollment->section_listing_id),
                    $delete_button
                ];
            }
            echo html_writer::table($table);
        } else {
            echo $OUTPUT->notification(get_string('teacher_enroll_not_found', 'enrol_workdaystudent'));
        }
    } else {
        echo $OUTPUT->notification(get_string('teacher_enroll_not_found', 'enrol_workdaystudent'));
    }
}

echo $OUTPUT->footer();

