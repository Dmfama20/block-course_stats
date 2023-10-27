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
 * Block plugin "block_course_stats" - lib.php
 * *
 * @package     block_course_stats
 * @copyright   2022 Alexander Dominicus, Bochum University of Applied Science <alexander.dominicus@hs-bochum.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

defined('MOODLE_INTERNAL') || die();

 // Function to count the total number of participants in a course
 function count_total_participants($courseId) {
    global $DB;

    $sql = "SELECT COUNT(DISTINCT ue.userid) as count
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            WHERE e.courseid = ?";

    $count = $DB->get_record_sql($sql, [$courseId]);

    return $count ? (int)$count->count : 0;
}

function block_course_stats_get_courses()   {
    global $DB;
            
    // Fetching context IDs where the block 'course_stats' is added
    $blockInstances = $DB->get_records_sql(
        "SELECT ctx.instanceid FROM {block_instances} bi
        JOIN {context} ctx ON bi.parentcontextid = ctx.id
        WHERE bi.blockname = 'course_stats' AND ctx.contextlevel = 50"
    );

    if (empty($blockInstances)) {
        return;
    }

    // Extract course IDs from the contexts
    $courseIds = array_map(function($obj) {
        return $obj->instanceid;
    }, $blockInstances);

    // Fetch only those courses where the block is added
    list($insql, $inparams) = $DB->get_in_or_equal($courseIds);
    $courses = $DB->get_records_select('course', "id $insql", $inparams);
    return $courses;

}

function block_course_stats_calculate_core_completion() {
    global $DB;

    // Get only courses where this block is added
    $courses= block_course_stats_get_courses() ;
 
    foreach ($courses as $course) {
        $completedUsers = $DB->get_records_sql(
            "SELECT userid FROM {course_completions} WHERE course = ? AND timecompleted IS NOT NULL",
            [$course->id]
        );
        $completedCount = count($completedUsers);
        
        if ($completedCount==0) {
            $totalCount = count_total_participants($course->id);
            $record = $DB->get_record('block_course_stats', ['courseid' => $course->id]);
            if ($record) {
                $record->minpoints = 0;
                $record->maxpoints = 0;
                $record->participants = $totalCount;
                $record->averagepoints = 0;  // Replace 'medianpoints' with 'averagepoints'
                $record->completed_count = $completedCount;
                $record->timestamp = time();
                $DB->update_record('block_course_stats', $record);
            } else {
                $newrecord = new \stdClass();
                $newrecord->courseid = $course->id;
                $newrecord->minpoints = 0;
                $newrecord->maxpoints = 0;
                $newrecord->participants = $totalCount;
                $newrecord->averagepoints = 0 ; // Replace 'medianpoints' with 'averagepoints'
                $newrecord->completed_count = $completedCount;
                $newrecord->timestamp = time();
                $DB->insert_record('block_course_stats', $newrecord);
            }
            continue;
        }

        $userids = array_keys($completedUsers);
        list($insql, $inparams) = $DB->get_in_or_equal($userids);

        $sql = "SELECT finalgrade FROM {grade_grades}
                WHERE userid $insql AND itemid IN (SELECT id FROM {grade_items} WHERE courseid = ? AND itemtype ='course')";
        $grades = $DB->get_records_sql($sql, array_merge($inparams, [$course->id]));

        $gradesArr = array_values(array_map(function($grade) {
            return $grade->finalgrade;
        }, $grades));

        if (!empty($gradesArr)) {
            // Initialize variables to store min and max
            $minPoints = min($gradesArr);  // or some other large number
            $maxPoints = max($gradesArr);  // or some other small number

            // Calculate average
            if($completedCount!=0)  {
                $average = array_sum($gradesArr) / $completedCount;
            }
            
        } else {
            // If no grades are available but users have completed the course
            $minPoints = 0;
            $maxPoints = 0;
            $average = 0;
        }
        // Count total participants
        $totalCount = count_total_participants($course->id);

        $record = $DB->get_record('block_course_stats', ['courseid' => $course->id]);

        if ($record) {
            $record->minpoints = $minPoints;
            $record->maxpoints = $maxPoints;
            $record->participants = $totalCount;
            $record->averagepoints = $average;  // Replace 'medianpoints' with 'averagepoints'
            $record->completed_count = $completedCount;
            $record->timestamp = time();
            $DB->update_record('block_course_stats', $record);
        } else {
            $newrecord = new \stdClass();
            $newrecord->courseid = $course->id;
            $newrecord->minpoints = $minPoints;
            $newrecord->maxpoints = $maxPoints;
            $newrecord->participants = $totalCount;
            $newrecord->averagepoints = $average;  // Replace 'medianpoints' with 'averagepoints'
            $newrecord->completed_count = $completedCount;
            $newrecord->timestamp = time();
            $DB->insert_record('block_course_stats', $newrecord);
        }
    }
}


function block_course_stats_get_completions($userid, $activities) {
    global $DB;
    if ($this->completions === null) {
        throw new coding_exception('completions not computed until for_user() or for_overview() is called');
    }
    if ($this->user) {
        // Filter to visible activities and fill in gaps.
        $completions = $this->completions[$this->user->id] ?? [];
        $completion = new completion_info($this->course);
        $ret = [];
         $cm = new stdClass();

        foreach ($this->visibleactivities as $activity) {
            $data= $DB->get_record('course_modules_completion',['coursemoduleid'=>$activity->id,'userid'=>$this->user->id ]);
            if($data)   {
                if($data->timemodified > 0 ) {
                    $ret[$activity->id] = $data->timemodified;
                    }
            }
            
        }
        return $ret;
    } else {
        throw new coding_exception('unimplemented');
    }
}

/**
 * Retrieve enrolled users for a specific course.
 *
 * @param int $courseid The ID of the course.
 * @return array List of enrolled users.
 */
function block_course_stats_get_enrolled_users_by_courseid($courseid) {
    global $DB;

    // Ensure the course exists.
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    // Get the context of the course.
    $context = context_course::instance($course->id);

    // Fetch enrolled users.
    $enrolled_users = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname ASC');

    return $enrolled_users;
}


function block_course_stats_get_completions_of_user($userid, $activities) {

        foreach ($activities as $activity) {
            $data= $DB->get_record('course_modules_completion',['coursemoduleid'=>$activity->id,'userid'=>$userid ]);
            if($data)   {
                if($data->timemodified > 0 ) {
                    continue;
                    }
                else {
                     return false;

                    }
                 }

            else {
                return false;
            }
            
        }

        return true;
    
}


function block_course_stats_calculate_own_completion($activities) {
    global $DB;

    // Get only courses where this block is added
    $courses= block_course_stats_get_courses() ;
 
    foreach ($courses as $course) {

        $CourseUsers = block_course_stats_get_enrolled_users_by_courseid($course->id);
        
        $completedUsers=[];

        foreach($CourseUsers as $user)  {
            // check if user has all activities completed
            if(block_course_stats_get_completions_of_user($user->id, $activities)) {
                array_push($completedUsers, $user->id);
            }

        }

        $completedCount=count($completedUsers);

        if ($completedCount==0) {
            $totalCount = count_total_participants($course->id);
            $record = $DB->get_record('block_course_stats', ['courseid' => $course->id]);
            if ($record) {
                $record->minpoints = 0;
                $record->maxpoints = 0;
                $record->participants = $totalCount;
                $record->averagepoints = 0;  // Replace 'medianpoints' with 'averagepoints'
                $record->completed_count = $completedCount;
                $record->timestamp = time();
                $DB->update_record('block_course_stats', $record);
            } else {
                $newrecord = new \stdClass();
                $newrecord->courseid = $course->id;
                $newrecord->minpoints = 0;
                $newrecord->maxpoints = 0;
                $newrecord->participants = $totalCount;
                $newrecord->averagepoints = 0 ; // Replace 'medianpoints' with 'averagepoints'
                $newrecord->completed_count = $completedCount;
                $newrecord->timestamp = time();
                $DB->insert_record('block_course_stats', $newrecord);
            }
            continue;
        }

    
        list($insql, $inparams) = $DB->get_in_or_equal( $completedUsers);

        $sql = "SELECT finalgrade FROM {grade_grades}
                WHERE userid $insql AND itemid IN (SELECT id FROM {grade_items} WHERE courseid = ? AND itemtype ='course')";
        $grades = $DB->get_records_sql($sql, array_merge($inparams, [$course->id]));

        $gradesArr = array_values(array_map(function($grade) {
            return $grade->finalgrade;
        }, $grades));

        if (!empty($gradesArr)) {
            // Initialize variables to store min and max
            $minPoints = min($gradesArr);  // or some other large number
            $maxPoints = max($gradesArr);  // or some other small number

            // Calculate average
            if($completedCount!=0)  {
                $average = array_sum($gradesArr) / $completedCount;
            }
            
        } else {
            // If no grades are available but users have completed the course
            $minPoints = 0;
            $maxPoints = 0;
            $average = 0;
        }
        // Count total participants
        $totalCount = count_total_participants($course->id);

        $record = $DB->get_record('block_course_stats', ['courseid' => $course->id]);

        if ($record) {
            $record->minpoints = $minPoints;
            $record->maxpoints = $maxPoints;
            $record->participants = $totalCount;
            $record->averagepoints = $average;  // Replace 'medianpoints' with 'averagepoints'
            $record->completed_count = $completedCount;
            $record->timestamp = time();
            $DB->update_record('block_course_stats', $record);
        } else {
            $newrecord = new \stdClass();
            $newrecord->courseid = $course->id;
            $newrecord->minpoints = $minPoints;
            $newrecord->maxpoints = $maxPoints;
            $newrecord->participants = $totalCount;
            $newrecord->averagepoints = $average;  // Replace 'medianpoints' with 'averagepoints'
            $newrecord->completed_count = $completedCount;
            $newrecord->timestamp = time();
            $DB->insert_record('block_course_stats', $newrecord);
        }
    }
}