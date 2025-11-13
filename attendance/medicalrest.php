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
 * Medical rest management page.
 *
 * @package    mod_attendance
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/outputcomponents.php');

use mod_attendance\form\medicalrest as medicalrest_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$attendance = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/attendance:manageattendances', $context);

$pageurl = new moodle_url('/mod/attendance/medicalrest.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title($course->shortname . ': ' . format_string($attendance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('course');
$PAGE->navbar->add(get_string('medicalrest', 'attendance'));

$manageurl = new moodle_url('/mod/attendance/manage.php', ['id' => $cm->id]);

$studentoptions = mod_attendance_medicalrest_student_options($context);
$form = null;
if (!empty($studentoptions)) {
    $form = new medicalrest_form(null, [
        'studentoptions' => $studentoptions,
    ]);
    $defaults = new stdClass();
    $defaults->registrationdate = time();
    $defaults->startdate = time();
    $defaults->enddate = time();
    $form->set_data($defaults);

    if ($form->is_cancelled()) {
        redirect($manageurl);
    }

    if ($data = $form->get_data()) {
        $now = time();
        $record = (object) [
            'attendanceid' => $attendance->id,
            'studentid' => $data->studentid,
            'description' => $data->description,
            'registrationdate' => $data->registrationdate,
            'startdate' => $data->startdate,
            'enddate' => $data->enddate,
            'createdby' => $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('attendance_medicalrests', $record);
        redirect($pageurl, get_string('medicalrestcreated', 'attendance'));
    }
}

$records = mod_attendance_medicalrest_records($attendance->id);

$output = $PAGE->get_renderer('mod_attendance');

echo $output->header();
echo $OUTPUT->heading(get_string('medicalrestheading', 'attendance'));

if (empty($studentoptions)) {
    echo $OUTPUT->notification(get_string('medicalreststudentsnotfound', 'attendance'), 'warning');
} else if ($form) {
    $form->display();
}

if ($records) {
    echo $OUTPUT->heading(get_string('medicalrestrecords', 'attendance'), 3);
    echo mod_attendance_render_medicalrest_table($records);
} else {
    echo $OUTPUT->notification(get_string('medicalrestempty', 'attendance'), 'info');
}

echo $output->footer();

/**
 * Return the student options available for this activity.
 *
 * @param context_module $context
 * @return array
 */
function mod_attendance_medicalrest_student_options(context_module $context): array {
    $fields = 'u.id, ' . get_all_user_name_fields(true, 'u');
    $users = get_enrolled_users($context, 'mod/attendance:view', 0, $fields, 'u.lastname ASC, u.firstname ASC');
    $options = [];
    foreach ($users as $user) {
        $options[$user->id] = fullname($user);
    }
    return $options;
}

/**
 * Fetch the medical rest records tied to an attendance instance.
 *
 * @param int $attendanceid
 * @return array
 */
function mod_attendance_medicalrest_records(int $attendanceid): array {
    global $DB;

    return $DB->get_records('attendance_medicalrests', ['attendanceid' => $attendanceid], 'registrationdate DESC, id DESC');
}

/**
 * Render the table that lists the recorded medical rests.
 *
 * @param array $records
 * @return string
 */
function mod_attendance_render_medicalrest_table(array $records): string {
    global $DB;

    if (empty($records)) {
        return '';
    }

    $userids = [];
    foreach ($records as $record) {
        $userids[$record->studentid] = true;
        $userids[$record->createdby] = true;
    }
    $users = [];
    if (!empty($userids)) {
        $fields = user_picture::fields();
        $users = $DB->get_records_list('user', 'id', array_keys($userids), '', $fields);
    }

    $table = new html_table();
    $table->head = [
        get_string('medicalreststudent', 'attendance'),
        get_string('medicalrestdescription', 'attendance'),
        get_string('medicalrestregistrationdate', 'attendance'),
        get_string('medicalrestperiod', 'attendance'),
        get_string('medicalrestauthor', 'attendance'),
    ];

    foreach ($records as $record) {
        $student = $users[$record->studentid] ?? null;
        $author = $users[$record->createdby] ?? null;
        $studentname = $student ? fullname($student) : get_string('unknownuser');
        $authorname = $author ? fullname($author) : get_string('unknownuser');
        $period = userdate($record->startdate) . ' - ' . userdate($record->enddate);
        $table->data[] = [
            format_string($studentname),
            format_text($record->description, FORMAT_PLAIN),
            userdate($record->registrationdate),
            $period,
            format_string($authorname),
        ];
    }

    return html_writer::table($table);
}
