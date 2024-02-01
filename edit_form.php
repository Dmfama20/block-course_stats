<?php

class block_course_stats_edit_form extends block_edit_form {


    function block_course_stats_get_activities( $config = null, $forceorder = null)
{
    global $COURSE;
    $courseid=$COURSE->id;
    $modinfo = get_fast_modinfo($courseid, -1);
    $sections = $modinfo->get_sections();
    $activities = [];
    foreach ($modinfo->instances as $module => $instances) {
        $modulename = get_string('pluginname', $module);
        foreach ($instances as $index => $cm) {
            if (
                $cm->completion != COMPLETION_TRACKING_NONE && (
                    $config == null || (
                        !isset($config->activitiesincluded) || (
                            $config->activitiesincluded != 'selectedactivities' ||
                                !empty($config->selectactivities) &&
                                in_array($module.'-'.$cm->instance, $config->selectactivities))))
            ) {
                $activities[] = [
                    'type' => $module,
                    'modulename' => $modulename,
                    'id' => $cm->id,
                    'instance' => $cm->instance,
                    'name' => format_string($cm->name),
                    'expected' => $cm->completionexpected,
                    'section' => $cm->sectionnum,
                    'position' => array_search($cm->id, $sections[$cm->sectionnum]),
                    'url' => method_exists($cm->url, 'out') ? $cm->url->out() : '',
                    'context' => $cm->context,
                    'icon' => $cm->get_icon_url(),
                    'available' => $cm->available,
                    'visible' => $cm->visible,
                ];
            }
        }
    }
    return $activities;
}


    protected function specific_definition($mform) {


        $activities =  $this->block_course_stats_get_activities( null, 'orderbycourse');
        $numactivies = count($activities);

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('selectyesno', 'config_useowncoursecompletion',get_string('useowncompletion', 'block_course_stats'));
        $mform->setDefault('config_useowncoursecompletion', 0);
        $mform->addHelpButton('config_useowncoursecompletion', 'why_use_own_completion', 'block_course_stats'); 
        // Grade needed to pass the course 
        $mform->addElement('text', 'config_points', get_string('pointsneeded', 'block_course_stats'));
        $mform->setType('config_points', PARAM_FLOAT);
        $mform->addHelpButton('config_points', 'why_use_points', 'block_course_stats');  
        $mform->hideif('config_points', 'config_useowncoursecompletion', 'neq', 1);
        // maximum grade of the course 
        $mform->addElement('text', 'config_maxpoints', get_string('pointsmax', 'block_course_stats'));
        $mform->setType('config_maxpoints', PARAM_FLOAT);
        $mform->addHelpButton('config_maxpoints', 'why_use_maxpoints', 'block_course_stats');  
        $mform->hideif('config_maxpoints', 'config_useowncoursecompletion', 'neq', 1);
        // passing grade of the course 
        $mform->addElement('text', 'config_passingpoints', get_string('pointspassing', 'block_course_stats'));
        $mform->setType('config_passingpoints', PARAM_FLOAT);
        $mform->addHelpButton('config_passingpoints', 'why_use_passingpoints', 'block_course_stats');  
        $mform->hideif('config_passingpoints', 'config_useowncoursecompletion', 'neq', 1);

        
        // Selected activities by the user
        $activitiestoinclude = [];
        foreach ($activities as $index => $activity) {
            $activitiestoinclude[$activity['id']] = $activity['section'].': '.$activity['name'];
        }
        $mform->addElement('select', 'config_coursestatsativities', get_string('selectactivities', 'block_course_stats'), $activitiestoinclude);
        $mform->getElement('config_coursestatsativities')->setMultiple(true);
        $mform->getElement('config_coursestatsativities')->setSize(count($activitiestoinclude));
        $mform->setAdvanced('config_coursestatsativities', true);
        $mform->hideif('config_coursestatsativities', 'config_useowncoursecompletion', 'neq', 1);

    }
}
