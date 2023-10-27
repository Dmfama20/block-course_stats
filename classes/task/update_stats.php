<?php


namespace block_course_stats\task;

defined('MOODLE_INTERNAL') || die();
class update_stats extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('updatestats', 'block_course_stats');
    }


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

function block_course_stats_get_instances()   {
    global $DB;
            
    // Fetching context IDs where the block 'course_stats' is added
    $blockInstances = $DB->get_records_sql(
        "SELECT * FROM {block_instances} bi
        JOIN {context} ctx ON bi.parentcontextid = ctx.id
        WHERE bi.blockname = 'course_stats' AND ctx.contextlevel = 50"
    );

   return $blockInstances;

}

function block_course_stats_calculate_core_completion($courseid) {
    global $DB;

        $completedUsers = $DB->get_records_sql(
            "SELECT userid FROM {course_completions} WHERE course = ? AND timecompleted IS NOT NULL",
            [$courseid]
        );
        $completedCount = count($completedUsers);
        
        if ($completedCount==0) {
            $totalCount = $this->count_total_participants($courseid);
            $record = $DB->get_record('block_course_stats', ['courseid' => $courseid]);
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
                $newrecord->courseid = $courseid;
                $newrecord->minpoints = 0;
                $newrecord->maxpoints = 0;
                $newrecord->participants = $totalCount;
                $newrecord->averagepoints = 0 ; // Replace 'medianpoints' with 'averagepoints'
                $newrecord->completed_count = $completedCount;
                $newrecord->timestamp = time();
                $DB->insert_record('block_course_stats', $newrecord);
            }
        
        }
        else{
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
            $totalCount = $this->count_total_participants($courseid);
    
            $record = $DB->get_record('block_course_stats', ['courseid' => $courseid]);
    
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
                $newrecord->courseid = $courseid;
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



function block_course_stats_get_enrolled_users_by_courseid($courseid) {
    global $DB;

    // Ensure the course exists.
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    


    // Get the context of the course.
    $context = \context_course::instance($courseid);

    // Fetch enrolled users.
    $enrolled_users = get_enrolled_users( $context, '', 0, 'u.*', 'u.lastname ASC');

    return $enrolled_users;
}


function block_course_stats_get_completions_of_user($userid, $activities) {
        global $DB;
        foreach ($activities as $activity) {
            $data= $DB->get_record('course_modules_completion',['coursemoduleid'=>$activity,'userid'=>$userid ]);
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


function block_course_stats_calculate_own_completion($activities,$courseid, $grade) {
    global $DB;

        $CourseUsers = $this->block_course_stats_get_enrolled_users_by_courseid($courseid);
        
        $completedUsers=[];

        foreach($CourseUsers as $user)  {
            // check first if user has all activities completed
            if($this->block_course_stats_get_completions_of_user($user->id, $activities)) {
                array_push($completedUsers, $user->id);
            }

        }

        
        $completedCount = count($completedUsers);

        if ($completedCount==0) {
            // Nobody completed the course
            $totalCount = $this->count_total_participants($courseid);
            $record = $DB->get_record('block_course_stats', ['courseid' => $courseid]);
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
                $newrecord->courseid = $courseid;
                $newrecord->minpoints = 0;
                $newrecord->maxpoints = 0;
                $newrecord->participants = $totalCount;
                $newrecord->averagepoints = 0 ; // Replace 'medianpoints' with 'averagepoints'
                $newrecord->completed_count = $completedCount;
                $newrecord->timestamp = time();
                $DB->insert_record('block_course_stats', $newrecord);
            }
           
        }
        else {
            // At least one person completed the course

        list($insql, $inparams) = $DB->get_in_or_equal( $completedUsers);

        // Now check grades of each user 

        if($grade > 0)  {  

            $sql = "SELECT finalgrade FROM {grade_grades}
            WHERE userid $insql AND itemid IN (SELECT id FROM {grade_items} WHERE courseid = ? AND itemtype ='course' AND finalgrade >= ?)";
            $grades = $DB->get_records_sql($sql, array_merge($inparams, [$courseid,$grade]));
            $completedCount=count($grades);
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
                
            } 
        }

        else {
            // If no grades are available but users have completed the course
            $minPoints = 0;
            $maxPoints = 0;
            $average = 0;
        }
        // Count total participants
        $totalCount = $this->count_total_participants($courseid);
        $record = $DB->get_record('block_course_stats', ['courseid' => $courseid]);
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
            $newrecord->courseid = $courseid;
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


    public function execute() {

            // Get only Blockinstances (including courseid) where this block is added
             $instances= $this->block_course_stats_get_instances() ;
             foreach($instances as $inst)   {
                $config= (unserialize_object(base64_decode(($inst->configdata))));
                // The instanceid is the course id
                $courseid= $inst->instanceid;
                // All acticties are stored in $config->coursestatsativities
                $activities = $config->coursestatsativities;
                $gradeneeded = $config->points;
                if($config->useowncoursecompletion) {
                    $this->block_course_stats_calculate_own_completion($activities,$courseid ,$gradeneeded);
                }
                else {
                    $this->block_course_stats_calculate_core_completion( $courseid);
                }
             }
    }   
}
