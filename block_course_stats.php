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
 * Newblock block caps.
 *
 * @package    block_course_stats
 * @copyright  Daniel Neis <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_course_stats extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_course_stats');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        
        global $DB, $COURSE;
        
        $courseid = $COURSE->id;
        
        // Fetch number of participants who have completed the course
        $completedCount = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course_completions} WHERE course = ? AND timecompleted IS NOT NULL",
            [$courseid]
        );

        // Fetch minimum and maximum points (grades)
        $sql = "SELECT MIN(finalgrade) as min_grade, MAX(finalgrade) as max_grade FROM {grade_grades} WHERE itemid IN (SELECT id FROM {grade_items} WHERE courseid = ?)";
        $minmax = $DB->get_record_sql($sql, [$courseid]);

        // Fetch all grades to calculate median
        $allGrades = $DB->get_records_sql(
            "SELECT finalgrade FROM {grade_grades} WHERE itemid IN (SELECT id FROM {grade_items} WHERE courseid = ?) ORDER BY finalgrade ASC",
            [$courseid]
        );

        $gradesArray = array();
        foreach($allGrades as $grade) {
            if(!is_null($grade->finalgrade)) {
                $gradesArray[] = $grade->finalgrade;
            }
        }

        $count = count($gradesArray);
        $median = 0;
        if ($count > 0) {
            sort($gradesArray);
            $middle = floor(($count - 1) / 2);

            if ($count % 2) {
                $median = $gradesArray[$middle];
            } else {
                $low = $gradesArray[$middle];
                $high = $gradesArray[$middle + 1];
                $median = (($low + $high) / 2);
            }
        }

        $this->content = new stdClass;
        $this->content->text = "Participants completed: $completedCount";
        $this->content->text .= "<br>Min Points: " . round($minmax->min_grade, 2);
        $this->content->text .= "<br>Max Points: " . round($minmax->max_grade, 2);
        $this->content->text .= "<br>Median Points: " . round($median, 2);
        
        return $this->content;
    }

    // my moodle can only have SITEID and it's redundant here, so take it away
    public function applicable_formats() {
        return array('all' => false,
                     'site' => true,
                     'site-index' => true,
                     'course-view' => true, 
                     'course-view-social' => false,
                     'mod' => true, 
                     'mod-quiz' => false);
    }

    public function instance_allow_multiple() {
          return true;
    }

    function has_config() {return true;}

    public function cron() {
            mtrace( "Hey, my cron script is running" );
             
                 // do something
                  
                      return true;
    }
}
