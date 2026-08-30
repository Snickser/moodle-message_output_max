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
 * The max message processor.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/message/output/lib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

/**
 * The max message processor
 *
 * @package   message_max
 * @copyright  2017 onwards Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_output_max extends message_output {
    /** @var string */
    protected mixed $manager;

    /**
     * Constructor to add needed properties to the app.
     */
    public function __construct() {
        $this->manager = new message_max\manager();
    }

    /**
     * Processes the message and sends a notification via max
     *
     * @param stdClass $eventdata the event data submitted by the message sender plus $eventdata->savedmessageid
     * @return true if ok, false if error
     */
    public function send_message($eventdata) {
        global $CFG;

        // Skip any messaging of suspended and deleted users.
        if (($eventdata->userto->auth === 'nologin') || $eventdata->userto->suspended || $eventdata->userto->deleted) {
            return true;
        }

        if (!empty($CFG->noemailever)) {
            // Hidden setting for development sites, set in config.php if needed.
            debugging('$CFG->noemailever is active, no max message sent.', DEBUG_MINIMAL);
            return true;
        }

        $message = $this->format_max_message($eventdata);
        if ($message === '') {
            return false;
        }

        return $this->manager->send_message($message, $eventdata->userto->id);
    }

    /**
     * Build plain-text MAX message from event data and admin template.
     *
     * @param stdClass $eventdata
     * @return string
     */
    protected function format_max_message(stdClass $eventdata): string {
        global $CFG, $SITE;

        $body = $this->extract_message_body($eventdata);
        $template = $this->manager->config('messagetemplate');
        if ($template === null || trim((string) $template) === '') {
            $template = '{message}';
        }

        $messagesurl = (new moodle_url('/message/index.php', ['id' => $eventdata->userto->id]))->out(false);
        $contexturl = '';
        if (!empty($eventdata->contexturl)) {
            $contexturl = (string) $eventdata->contexturl;
        }

        $replacements = [
            '{message}' => $body,
            '{subject}' => isset($eventdata->subject) ? (string) $eventdata->subject : '',
            '{sitename}' => format_string($SITE->fullname, true),
            '{wwwroot}' => $CFG->wwwroot,
            '{messagesurl}' => $messagesurl,
            '{contexturl}' => $contexturl,
            '{fullname}' => fullname($eventdata->userto),
            '{firstname}' => $eventdata->userto->firstname ?? '',
            '{lastname}' => $eventdata->userto->lastname ?? '',
        ];

        return strtr((string) $template, $replacements);
    }

    /**
     * Plain-text body without Moodle email-style footer.
     *
     * @param stdClass $eventdata
     * @return string
     */
    protected function extract_message_body(stdClass $eventdata): string {
        $full = $this->to_plain_text($eventdata->fullmessage ?? '');
        $body = $this->strip_moodle_message_footer($full);

        if (trim($body) === '') {
            $body = $this->to_plain_text($eventdata->smallmessage ?? '');
        }

        return trim($body);
    }

    /**
     * @param string $text
     * @return string
     */
    protected function to_plain_text(string $text): string {
        $text = strip_tags($text);
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Remove trailing Moodle "----- / This is a copy of a message..." footer.
     *
     * @param string $text
     * @return string
     */
    protected function strip_moodle_message_footer(string $text): string {
        // Line of mostly dashes (Moodle email separator), then footer until EOF.
        $stripped = preg_replace('/\R-{3,}[^\S\r\n]*\R[\s\S]*\z/u', '', $text);
        if ($stripped === null) {
            return $text;
        }
        return $stripped;
    }

    /**
     * Creates necessary fields in the messaging config form.
     *
     * @param array $preferences An object of user preferences
     */
    public function config_form($preferences) {
        global $USER;

        // If MAX has not been configured, do nothing.
        if (!$this->is_system_configured()) {
            return get_string('notconfigured', 'message_max');
        } else {
            return $this->manager->config_form($preferences, $USER->id);
        }
    }

    /**
     * Parses the submitted form data and saves it into preferences array.
     *
     * @param stdClass $form preferences form class
     * @param array $preferences preferences array
     */
    public function process_form($form, &$preferences) {
        return $this->manager->set_chatid();
    }

    /**
     * Loads the config data from database to put on the form during initial form display.
     *
     * @param object $preferences preferences object
     * @param int $userid the user id
     */
    public function load_data(&$preferences, $userid) {
        $preferences->max_chatid = get_user_preferences('message_processor_max_chatid', '', $userid);
    }

    /**
     * Tests whether the MAX settings have been configured on user level
     * @param  object $user the user object, defaults to $USER.
     * @return bool has the user made all the necessary settings
     * in their profile to allow this plugin to be used.
     */
    public function is_user_configured($user = null) {
        global $USER;

        if ($user === null) {
            $user = $USER;
        }
        return ($this->manager->is_chatid_set($user->id));
    }

    /**
     * Tests whether the MAX settings have been configured
     * @return boolean true if MAX is configured
     */
    public function is_system_configured() {
        return (!empty(get_config('message_max', 'sitebotname')) && !empty(get_config('message_max', 'sitebottoken')));
    }
}
