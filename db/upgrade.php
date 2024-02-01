<?php
defined('MOODLE_INTERNAL') || die();


function xmldb_block_course_stats_upgrade($oldversion): bool {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2024012500)  {

        // Define table block_course_stats to be created.
        $table = new xmldb_table('block_course_stats');

        // Adding fields to table block_course_stats.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('minpoints', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('maxpoints', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('averagepoints', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('completed_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('participants', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timestamp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('owncoursecompletion', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxgradescourse', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('gradepassingcourse', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table block_course_stats.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for block_course_stats.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Course_stats savepoint reached.
        upgrade_block_savepoint(true, XXXXXXXXXX, 'course_stats');
    }

    // Everything has succeeded to here. Return true.
    return true;
}