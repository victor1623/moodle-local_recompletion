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
 * Lesson handler event.
 *
 * @package    local_recompletion
 * @copyright  2023 Viktor Subbota, OVGU Magdeburg
 * @copyright  based on code by Dan Marsden from Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace local_recompletion\plugins;

use admin_setting_configcheckbox;
use admin_setting_configselect;
use coding_exception;
use dml_exception;
use lang_string;
use stdClass;

/**
 * Lesson handler event.
 *
 * @package    local_recompletion
 * @copyright  2023 Viktor Subbota, OVGU Magdeburg
 * @copyright  based on code by Dan Marsden from Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class mod_lesson {
    /**
     * Add params to form.
     * @param moodleform $mform
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function editingform($mform) : void {
        $config = get_config('local_recompletion');

        $cba = array();
        $cba[] = $mform->createElement('radio', 'lesson', '',
            get_string('donothing', 'local_recompletion'), LOCAL_RECOMPLETION_NOTHING);
        $cba[] = $mform->createElement('radio', 'lesson', '',
            get_string('delete', 'local_recompletion'), LOCAL_RECOMPLETION_DELETE);

        $mform->addGroup($cba, 'lesson', get_string('lessonattempts', 'local_recompletion'), array(' '), false);
        $mform->addHelpButton('lesson', 'lessonattempts', 'local_recompletion');
        $mform->setDefault('lesson', $config->lessonattempts);

        $mform->addElement('checkbox', 'archivelesson',
            get_string('archive', 'local_recompletion'));
        $mform->setDefault('archivelesson', $config->archivelesson);

        $mform->disabledIf('lesson', 'enable', 'notchecked');
        $mform->disabledIf('archivelesson', 'enable', 'notchecked');
        $mform->hideIf('archivelesson', 'lesson', 'noteq', LOCAL_RECOMPLETION_DELETE);
    }

    /**
     * Add sitelevel settings for this plugin.
     *
     * @param admin_settingpage $settings
     */
    public static function settings($settings) {
        $choices = array(LOCAL_RECOMPLETION_NOTHING => new lang_string('donothing', 'local_recompletion'),
                         LOCAL_RECOMPLETION_DELETE => new lang_string('delete', 'local_recompletion'));

        $settings->add(new admin_setting_configselect('local_recompletion/lessonattempts',
            new lang_string('lessonattempts', 'local_recompletion'),
            new lang_string('lessonattempts_help', 'local_recompletion'), LOCAL_RECOMPLETION_NOTHING, $choices));

        $settings->add(new admin_setting_configcheckbox('local_recompletion/archivelesson',
            new lang_string('archivelesson', 'local_recompletion'), '', 1));
    }

    /**
     * Reset and archive lesson records.
     * @param int $userid - userid
     * @param stdclass $course - course record.
     * @param stdClass $config - recompletion config.
     */
    public static function reset(int $userid, stdClass $course, stdClass $config) {
        global $DB;
        if (empty($config->lesson)) {
            return;
        } else if ($config->lesson == LOCAL_RECOMPLETION_DELETE) {
            $params = array('userid' => $userid, 'course' => $course->id);
            $selectsql = 'userid = ? AND lessonid IN (SELECT id FROM {lesson} WHERE course = ?)';
            if ($config->archivelesson) {
                $lessonattempts = $DB->get_records_select('lesson_attempts', $selectsql, $params);
                foreach ($lessonattempts as $lid => $unused) {
                    // Add courseid to records to help with restore process.
                    $lessonattempts[$lid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_la', $lessonattempts);

                $lessongrades = $DB->get_records_select('lesson_grades', $selectsql, $params);
                foreach ($lessongrades as $lid => $unused) {
                    // Add courseid to records to help with restore process.
                    $lessongrades[$lid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_lg', $lessongrades);

                $lessonbranch = $DB->get_records_select('lesson_branch', $selectsql, $params);
                foreach ($lessonbranch as $lid => $unused) {
                    // Add courseid to records to help with restore process.
                    $lessonbranch[$lid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_lb', $lessonbranch);
                $lessontimer = $DB->get_records_select('lesson_timer', $selectsql, $params);
                foreach ($lessontimer as $lid => $unused) {
                    // Add courseid to records to help with restore process.
                    $lessontimer[$lid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_lt', $lessontimer);
            }
            $DB->delete_records_select('lesson_attempts', $selectsql, $params);
            $DB->delete_records_select('lesson_grades', $selectsql, $params);
            $DB->delete_records_select('lesson_branch', $selectsql, $params);
            $DB->delete_records_select('lesson_timer', $selectsql, $params);
        }
    }
}
