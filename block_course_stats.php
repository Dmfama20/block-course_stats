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
        global $DB;
        $this->title = get_string('pluginname', 'block_course_stats');
        
    }   

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        global $DB, $COURSE;
       
       
        $courseid = $COURSE->id;
        $this->content = new stdClass;

        if($this->config->useowncoursecompletion && count($this->config->coursestatsativities)==0)  {
            $this->content->text = "Sie haben in den Blockeinstellungen den eigenen Kursabschluss definiert jedoch keine Aktivitäten ausgewählt. Bitte wählen Sie mindestens eine Aktivät aus.";
            return $this->content;

        }
        // Fetch stats from block_course_stats table
        $stats = $DB->get_record('block_course_stats', array('courseid' => $courseid));
       
        if ($stats) {
            if($stats->completed_count>0)   {

            $this->content->text = get_string('participantscompleted', 'block_course_stats').$stats->completed_count."/".$stats->participants;
            $this->content->text .= "<br>". get_string('minpoints', 'block_course_stats')  . round($stats->minpoints, 2);
            $this->content->text .= "<br>".get_string('maxpoints', 'block_course_stats')   . round($stats->maxpoints, 2);
            $this->content->text .= "<br>".get_string('medianpoints', 'block_course_stats')  .  round($stats->averagepoints, 2);
            
        
            }
            else {
                $this->content->text = get_string('nocompletions', 'block_course_stats');
            }

        } else {
            $this->content->text = get_string('nocompletions', 'block_course_stats');
           
        }
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
