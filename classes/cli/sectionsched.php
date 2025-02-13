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

// Get settings.
$s = workdaystudent::get_settings();

// Get the sections.
$periods = workdaystudent::get_current_periods($s);

    function class_schedule(object $section, string $timezone = 'UTC'): array {
        // Grab the schedule.
        $schedule = $section->Section_Components;

        // Split the string into days and time.
        [$dayspart, $timepart] = explode('|', $schedule);

        // Trim the whitespace.
        $dayspart = trim($dayspart);
        $timepart = trim($timepart);

        // Split the days into an array.
        $days = explode(' ', $dayspart);

        // Check if we have both start and end time in the time part.
        $times = explode('-', $timepart);

        // Trim the times.
        $times = array_map('trim', $times);

        // Ensure we have exactly 2 times (start and end).
        if (count($times) < 2) {

            // If we don't have both start and end times, log the issue and exit.
            mtrace("Invalid time format: $timepart");
            return [];
        }

        // Split the time range into start and end times.
        [$starttime, $endtime] = $times;

        // Map full day names to short names as per my whimsy.
        $dayshortnames = [
            'Monday' => 'M', 'Tuesday' => 'Tu', 'Wednesday' => 'W', 'Thursday' => 'Th',
            'Friday' => 'F', 'Saturday' => 'Sa', 'Sunday' => 'Su'
        ];

        // Utilize an anonymous function to map the day to its shortened counterpart.
        $shortdays = array_map(fn($day) => $dayshortnames[$day] ?? $day, $days);

        // Set the timezone.
        $tz = new DateTimeZone($timezone);

        // Build an array to hold this stuff.
        $schedule_items = [];

        // Loop through the days and times, ensuring each day has a corresponding time.
        foreach ($days as $index => $day) {

            // If there are fewer times than days, we should repeat the times or handle the mismatch.
            if (empty($starttime) || empty($endtime)) {
                // Skip days with invalid time or log an error.
                mtrace("Invalid time for day: $day");
                continue;
            }

            // Convert to DateTime objects with the timezone.
            $startdatetime = DateTime::createFromFormat('g:i A', $starttime, $tz);
            $enddatetime = DateTime::createFromFormat('g:i A', $endtime, $tz);

            // Check if the DateTime creation was successful.
            if ($startdatetime === false || $enddatetime === false) {
                // Handle the error (skip and log it).
                mtrace("Failed to parse time: Start time - $starttime, End time - $endtime");
                continue;
            }

            // Create an object for each day with start or end times.
            $schedule_items[] = (object)[
                'section_listing_id' => $section->Section_Listing_ID, // Add section_listing_id to each object
                'day' => $day,
                'short_day' => $shortdays[$index] ?? null,
                'start_time' => $startdatetime->setTimezone($tz)->format('g:i A T'),
                'end_time' => $enddatetime->setTimezone($tz)->format('g:i A T')
            ];
        }

        // Return the array of schedule items.
        return $schedule_items;
    }

    function insert_schedule($schedules) {
        global $DB;

        // Build the data array.
        $data = array();

        // Loop through the schedules and insert them as we get them.
        foreach($schedules as $schedule) {
            // Do the nasty.
            $data[] = $DB->insert_record('enrol_wds_section_meta', $schedule);
        }

        return $data;
    }

$section = new stdClass();
$section->Section_Listing_ID = "COURSE_SECTION_87436534";
//$section->Section_Components = "Monday | 5:30 PM - 8:30 PM";
$section->Section_Components = "Monday Tuesday Wednesday Thursday Friday | 1:40 PM - 3:50 PM";

$result = class_schedule($section, 'America/Chicago');
var_dump($result);
