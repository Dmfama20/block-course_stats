<?php
namespace block_course_stats\task;

defined('MOODLE_INTERNAL') || die();

class update_stats extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('updatestats', 'block_course_stats');
    }


    // Function to count the total number of participants in a course
private function count_total_participants($courseId) {
    global $DB;

    $sql = "SELECT COUNT(DISTINCT ue.userid) as count
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            WHERE e.courseid = ?";

    $count = $DB->get_record_sql($sql, [$courseId]);

    return $count ? (int)$count->count : 0;
}


public function execute() {
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

        // Now continue with the rest of your code to calculate and store stats.

    foreach ($courses as $course) {
        $completedUsers = $DB->get_records_sql(
            "SELECT userid FROM {course_completions} WHERE course = ? AND timecompleted IS NOT NULL",
            [$course->id]
        );
        $completedCount = count($completedUsers);
        
        if ($completedCount==0) {
            $totalCount = $this->count_total_participants($course->id);
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
        $totalCount = $this->count_total_participants($course->id);

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
}
