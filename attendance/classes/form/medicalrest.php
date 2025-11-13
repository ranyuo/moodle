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
 * Form used to capture student medical rest information.
 *
 * @package    mod_attendance
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Medical rest form definition.
 */
class medicalrest extends \moodleform {
    /**
     * Define form elements.
     */
    public function definition() {
        $mform = $this->_form;
        $studentoptions = $this->_customdata['studentoptions'] ?? [];

        $mform->addElement('select', 'studentid', get_string('medicalreststudent', 'attendance'), $studentoptions);
        $mform->addRule('studentid', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('medicalrestdescription', 'attendance'), ['rows' => 4, 'cols' => 50]);
        $mform->addRule('description', null, 'required', null, 'client');
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('date_selector', 'registrationdate', get_string('medicalrestregistrationdate', 'attendance'));
        $mform->addRule('registrationdate', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'startdate', get_string('medicalreststartdate', 'attendance'));
        $mform->addRule('startdate', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'enddate', get_string('medicalrestenddate', 'attendance'));
        $mform->addRule('enddate', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('savemedicalrest', 'attendance'));
    }

    /**
     * Validate the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['startdate']) && !empty($data['enddate']) && $data['enddate'] < $data['startdate']) {
            $errors['enddate'] = get_string('medicalrestinvalidrange', 'attendance');
        }

        return $errors;
    }
}
