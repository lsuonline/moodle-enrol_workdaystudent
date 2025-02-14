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
 * Setting up the scheduled tasks.
 *
 * @package    enrol_workdaystudent
 * @copyright  2023 onwards LSUOnline & Continuing Education
 * @copyright  2023 onwards Robert Russo
 */

defined('MOODLE_INTERNAL') || die();

// Define the task defaults.
$tasks = array(
    array(
        'classname' => 'enrol_workdaystudent\task\workdaystudent_full_enroll',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),
    array(
        'classname' => 'enrol_workdaystudent\task\workdaystudent_quick_enroll',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '3',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    )

);
