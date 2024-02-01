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

function block_course_stats_get_course_final_grades($course_id) {
    global $DB;

    // Array to hold the final grades
    $final_grades = array();

    // Get the grade items for the course
    $grade_items = grade_item::fetch_all(array('courseid' => $course_id, 'itemtype' => 'course'));

    // Check if there is a course grade item
    if (empty($grade_items)) {
        return $final_grades; // Return empty array if no grade items found
    }

    // Assuming there's only one course grade item
    $course_grade_item = reset($grade_items);

    // Get enrolled users in the course
    $context = context_course::instance($course_id);
    $enrolled_users = get_enrolled_users($context);

    // Loop through each user and get their final grade
    foreach ($enrolled_users as $user) {
        $grade = $course_grade_item->get_grade($user->id, true);
        $final_grade = $grade ? $grade->finalgrade : null; // Get the final grade, if available

        // Add the final grade to the array
        $final_grades[$user->id] = $final_grade;
    }

    return $final_grades;
}


function block_course_stats_calculate_own_completion($activities,$courseid, $gradecompletecourse, $gradepasscourse, $maxgradecourse) {
    global $DB;
        // First, get all partcipants in the course
        $CourseUsers = $this->block_course_stats_get_enrolled_users_by_courseid($courseid);
        $completedUsers=[];
        $finalgradesall= [];
        $finalgradescompleted = [];
        // Second, find those participants who completed all neccessary activites and get final grades.
        //  Moreover, calculate final grade of all partcipants
        // Returns an array of user ids
        foreach($CourseUsers as $user)  {
            // check first if user has all activities completed
            if($this->block_course_stats_get_completions_of_user($user->id, $activities)) {
                array_push($completedUsers, $user->id);
                $sql = "SELECT finalgrade FROM {grade_grades}
                WHERE userid = ? AND itemid IN (SELECT id FROM {grade_items} WHERE courseid = ? AND itemtype ='course' AND  finalgrade >= ?)";
                $sqlarray=[$user->id,$courseid,$gradepasscourse ];
                $grade = $DB->get_record_sql($sql,$sqlarray );
                // echo var_dump($grade);
                array_push($finalgradescompleted,$grade->finalgrade);
            }
            $sql = "SELECT finalgrade FROM {grade_grades}
            WHERE userid = ? AND itemid IN (SELECT id FROM {grade_items} WHERE courseid = ? AND itemtype ='course' )";
            $sqlarray=[$user->id,$courseid];
            $grade = $DB->get_record_sql($sql,$sqlarray );
            // echo var_dump($grade);
            array_push($finalgradesall,$grade->finalgrade);
        }
        // echo var_dump($finalgradescompleted);
        $completedCount = count($completedUsers);
            // Nobody completed the course
        if ($completedCount==0) {
            $totalCount = $this->count_total_participants($courseid);
            $record = $DB->get_record('block_course_stats', ['courseid' => $courseid]);
            if ($record) {
                $record->minpoints = 0;
                $record->maxpoints = 0;
                $record->participants = $totalCount;
                $record->averagepoints = 0;  // Replace 'medianpoints' with 'averagepoints'
                $record->completed_count = $completedCount;
                $record->owncoursecompletion = 1;
                $record->maxgradescourse = $maxgradecourse;
                $record->gradepassingcourse = $gradepasscourse;
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
                $newrecord->owncoursecompletion = 1;
                $newrecord->maxgradescourse = $maxgradecourse;
                $newrecord->gradepassingcourse = $gradepasscourse;
                $newrecord->timestamp = time();
                $DB->insert_record('block_course_stats', $newrecord);
            }
        }
         // At least one person completed the course
        else {
        if($gradecompletecourse > 0)  {  
           
            // Calculate min and max of all partcipants completed the course
            if (!empty($finalgradescompleted)) {
                // Initialize variables to store min and max/ Remove NULL-Entries
                $minPoints = min(array_filter($finalgradescompleted,'strlen'));  // or some other large number
                $maxPoints = max(array_filter($finalgradescompleted,'strlen'));  // or some other small number
                // Calculate average
                if($completedCount!=0)  {
                    $average = array_sum($finalgradescompleted) / $completedCount;
                }
            } 
        }
        else {
            // If no grades are available but users have completed the course
            $minPoints = 0;
            $maxPoints = 0;
            $average = 0;
        }
        echo var_dump($courseid);
        echo var_dump($finalgradescompleted);
        // echo var_dump($minPoints);
        // Count total participants
        $totalCount = $this->count_total_participants($courseid);
      
        $number = $gradepasscourse;

        $gtn = array_filter($finalgradesall, function($value) use ($number) { return $value > $number; });
        // echo count($gtn); 
        $countpassed = count($gtn);
        $record = $DB->get_record('block_course_stats', ['courseid' => $courseid]);
        if ($record) {
            $record->minpoints = $minPoints;
            $record->maxpoints = $maxPoints;
            $record->participants = $totalCount;
            $record->averagepoints = $average;  // Replace 'medianpoints' with 'averagepoints'
            $record->completed_count = $completedCount;
            $record->owncoursecompletion = 1;
            $record->maxgradescourse = $maxgradecourse;
            $record->gradepassingcourse = $countpassed;
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
            $newrecord->owncoursecompletion = 1;
            $newrecord->maxgradescourse = $maxgradecourse;
            $newrecord->gradepassingcourse = $countpassed;
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
                $gradepasscourse = $config->passingpoints;
                $maxgrade = $config->maxpoints;
                $gradecompletecourse = $config->points; 
                if($config->useowncoursecompletion) {
                    $this->block_course_stats_calculate_own_completion($activities,$courseid ,$gradecompletecourse,$gradepasscourse, $maxgrade);
                }
                else {
                    $this->block_course_stats_calculate_core_completion( $courseid);
                }
             }
    }   
}
