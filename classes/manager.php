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
 * MAX message plugin version information.
 *
 * @package     message_max
 * @copyright   2025 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace message_max;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/filelib.php');

require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');


/**
 * MAX helper manager class
 *
 * @author  Mike Churchward
 * @copyright  2017 onwards Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * @var $secretprefix A variable used to identify that chatid had not been set for the user.
     */
    private $secretprefix = 'usersecret::';

    /**
     * @var $curl The curl object used in this run. Avoids continuous creation of a curl object.
     */
    private $curl = null;

    /** @var string */
    public $config;

    /** @var string */
    public $courseid;

    /**
     * Constructor. Loads all needed data.
     */
    public function __construct() {
        $this->config = get_config('message_max');
    }

    /**
     * Send the message to MAX.
     * @param string $message The message contect to send to Slack.
     * @param int $userid The Moodle user id that is being sent to.
     */
    public function send_message($message, $userid) {
        global $CFG;

        if (empty($this->config('sitebottoken'))) {
            return true;
        } else if (empty($chatid = get_user_preferences('message_processor_max_chatid', '', $userid))) {
            return true;
        }

        $today = date("Y-m-d H:i:s");

        if ($this->config('parsemode') == 'HTML') {
            $message = strip_tags($message, "<b><strong><i><em><a><u><ins><code><pre><blockquote><tg-spoiler><tg-emoji>");
        } else if ($this->config('striptags')) {
            $message = html_to_text($message);
        }
        $message = mb_substr($message, 0, 4000, 'UTF-8');

        if ($this->config('tgext')) {
            if (is_file($this->config('tgext')) && is_executable($this->config('tgext'))) {
                $fp = popen($this->config('tgext'), "wb");
                fwrite($fp, $chatid . "\n" . $message);
                pclose($fp);
                $response = (object)["ok" => true];
            } else {
                $response = (object)["ok" => false, "error_code" => '404', "description" => $this->config('tgext')];
            }
        } else {
            $response = $this->send_api_command(
                'messages?user_id=' . $chatid . '&disable_link_preview=true',
                [
                 'text' => $message,
                 'format' => 'html',
                ],
                1,
            );
            if (!isset($response->message->recipient->user_id)) {
                $fname = $CFG->tempdir . '/max/';
                // Check if spool dir not exist.
                if (!is_dir($fname)) {
                    mkdir($fname);
                }
                $fname .= uniqid(time(), true);
                file_put_contents($fname, $chatid . "\n" . $message, FILE_APPEND | LOCK_EX);
            }
        }

        if ($this->config('maxlog')) {
            $buff = $today . " " . $userid . " " . $chatid . " " . mb_strlen($message);
            if (isset($response->message->body->mid)) {
                $buff .= " " . $response->message->body->mid;
            } else {
                $buff .= " ERROR " . serialize($response);
            }
            $buff .= "\n";
            if ($this->config('maxlogdump')) {
                $buff .= $message . "\n";
            }
            $fname = $CFG->tempdir . '/max.log';
            file_put_contents($fname, $buff . "\n", FILE_APPEND | LOCK_EX);
        }

        return (!empty($response) && isset($response->ok) && ($response->ok == true));
    }

    /**
     * Set the config item to the specified value, in the object and the database.
     * @param string $name The name of the config item.
     * @param string $value The value of the config item.
     */
    public function set_config($name, $value) {
        set_config($name, $value, 'message_max');
        $this->config->{$name} = $value;
    }
    /**
     * Return the requested configuration item or null. Should have been loaded in the constructor.
     * @param string $configitem The requested configuration item.
     * @return mixed The requested value or null.
     */
    public function config($configitem) {
        return isset($this->config->{$configitem}) ? $this->config->{$configitem} : null;
    }

    /**
     * Return the HTML for the user preferences form.
     * @param array $preferences An array of user preferences.
     * @param int $userid Moodle id of the user in question.
     * @return string The HTML for the form.
     */
    public function config_form($preferences, $userid) {
        // If the chatid is not set, display the link to do this.
        if (!$this->is_chatid_set($userid, $preferences)) {
            // Temporarily set the user's chatid to the sesskey value for security.
            $key = $this->set_usersecret($userid);
            $url = 'https://max.ru/' . $this->config('sitebotusername') . '?start=' . $key;

            $configbutton = get_string('connectinstructions', 'message_max', $this->config('sitebotname'));
            $configbutton .= '<div align="center"><a href="' . $url . '" target="_blank">' .
                get_string('connectme', 'message_max') . '</a></div>';
        } else {
            $url = new \moodle_url($this->redirect_uri(), ['action' => 'removechatid', 'userid' => $userid,
                'sesskey' => sesskey()]);
            $configbutton = '<a target=_blank href="https://max.ru/' . $this->config('sitebotusername') .
            '?start">https://max.ru/' . $this->config('sitebotusername') . '</a>' . '<br><br><a href="' . $url . '">' .
            get_string('removemax', 'message_max') . '</a>';
        }

        return $configbutton;
    }

    /**
     * Construct a variable used only by the plugin to help ensure user identity.
     * @return string A constructed variable for this user (Moodle's sesskey).
     */
    public function usersecret() {
        if ($this->config('webhook')) {
            return bin2hex(random_bytes(8));
        } else {
            return sesskey();
        }
    }

    /**
     * Set the user's chat id to the usersecret to allow for secure chat id identification.
     * @param int $userid The id of the user to set this for.
     * @return boolean Success or failure.
     */
    public function set_usersecret($userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if ($userid != $USER->id) {
            require_capability('moodle/site:config', \context_system::instance());
        }

        $secret = $this->usersecret();
        if (set_user_preference('message_processor_max_chatid', $this->secretprefix . $secret, $userid)) {
            return $secret;
        }
        return false;
    }

    /**
     * Check that the received usersecret matches the user's usersecret stored in the database.
     * @param string $receivedsecret The secret to test against the stored one.
     * @param int $userid The id of the user to set this for.
     * @return boolean Success or failure.
     */
    private function usersecret_match($receivedsecret, $userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if ($userid != $USER->id && !$this->config('webhook')) {
            require_capability('moodle/site:config', \context_system::instance());
        }

        $usersecret = substr(get_user_preferences('message_processor_max_chatid', '', $userid), strlen($this->secretprefix));
        return ($usersecret === $receivedsecret);
    }

    /**
     * Verify that a user has their chat id set.
     * @param int $userid The id of the user to check.
     * @param object $preferences Contains the MAX user preferences for the user, if present.
     * @return boolean True if the id is set.
     */
    public function is_chatid_set($userid, $preferences = null) {
        if ($preferences === null) {
            $preferences = new \stdClass();
        }
        if (!isset($preferences->max_chatid)) {
            $preferences->max_chatid = get_user_preferences('message_processor_max_chatid', '', $userid);
        }
        return (!empty($preferences->max_chatid) && (strpos($preferences->max_chatid, $this->secretprefix) !== 0));
    }

    /**
     * Return the redirect URI to handle the callback for OAuth.
     * @return string The URI.
     */
    public function redirect_uri() {
        global $CFG;

        return $CFG->wwwroot . '/message/output/max/maxconnect.php';
    }

    /**
     * Given a valid bot token, get the name and username of the bot.
     */
    public function update_bot_info() {
        if (empty($this->config('sitebottoken'))) {
            return false;
        } else {
            $response = $this->send_api_command('me');
            if ($response->user_id) {
                $this->set_config('sitebotname', $response->first_name);
                $this->set_config('sitebotusername', $response->username);
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Get the latest information from the Slack bot, and see if the user has initiated a connection.
     * Only needed if no webHook has been created.
     * @param int $userid The id of the user in question.
     * @return boolean Success.
     */
    public function set_chatid($userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if (empty($this->config('sitebottoken'))) {
            return false;
        } else {
            $results = $this->get_updates();
            if ($results !== false) {
                foreach ($results->updates as $object) {
                    if (isset($object->user)) {
                        if ($this->usersecret_match($object->payload)) {
                            set_user_preference('message_processor_max_chatid', $object->user->user_id, $userid);
                            $this->set_customprofile_username($userid, $object->user->name);
                            $this->send_message(get_string('welcome', 'message_max'), $userid);
                            break;
                        }
                    }
                }
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Remove the user's MAX chat id from the preferences.
     * @param int $userid The id to be cleared.
     * @return string Any information message.
     */
    public function remove_chatid($userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        } else if ($userid != $USER->id) {
            require_capability('moodle/site:config', \context_system::instance());
        }
        unset_user_preference('message_processor_max_chatid', $userid);

        return '';
    }

    /**
     * Set the webhook for this site into the MAX Bot.
     * @return string Empty if successful, otherwise the error message.
     */
    public function set_webhook() {
        $message = false;
        if (empty($this->config('sitebottoken'))) {
            $message = get_string('sitebottokennotsetup', 'message_max');
        } else {
            $url = new \moodle_url('/message/output/max/webhook.php');
            $response = $this->send_api_command('subscriptions', ['url' => $url->out(),
            'secret' => $this->config('sitebotsecret'),
            ], 1);
            if (!empty($response) && isset($response->success) && ($response->success == true)) {
                $this->set_config('webhook', '1');
                $message = 'Webhook is set successfully';
            } else if (!empty($response) && isset($response->message) && isset($response->message)) {
                $message = $response->message;
            }
        }
        return $message;
    }

    /**
     * Remove the webhook for this site into the MAX Bot.
     * @return string Empty if successful, otherwise the error message.
     */
    public function remove_webhook() {
        $message = false;
        $url = new \moodle_url('/message/output/max/webhook.php');
        $response = $this->send_api_command('subscriptions?url=' . $url->out(), null, 2);
        if (!empty($response) && isset($response->success) && ($response->success == true)) {
            $this->set_config('webhook', '0');
            $message = 'Webhook removed';
        } else if (!empty($response) && isset($response->success) && isset($response->message)) {
            $message = $response->message;
        }

        return $message;
    }

    /**
     * Returns the results of a getUpdates API request.
     * @return object The JSON decoded results object.
     */
    public function get_updates() {
        $response = $this->send_api_command('updates');
        if ($response) {
            return $response;
        } else {
            return false;
        }
    }

    /**
     * Send a MAX API command and return the results.
     * @param string $command The API command to send.
     * @param array $params The parameters to send to the API command. Can be ommited.
     * @param int $method Get or Post method. Can be ommited.
     * @return object The JSON decoded return object.
     */
    public function send_api_command($command, $params = null, $method = 0) {
        if (empty($this->config('sitebottoken'))) {
            return false;
        }

        $this->curl = new \curl();

        $options = [
         'CURLOPT_RETURNTRANSFER' => true,
         'CURLOPT_TIMEOUT' => 30,
         'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
         'CURLOPT_SSLVERSION' => CURL_SSLVERSION_TLSv1_2,
         'CURLOPT_HTTPHEADER' => [
            'Authorization: ' . $this->config('sitebottoken'),
            'Content-Type: application/json',
         ],
        ];

        $location = 'https://platform-api.max.ru/' . $command;

        if ($method == 1) {
            $payload = json_encode($params, JSON_UNESCAPED_UNICODE);
            $response = $this->curl->post($location, $payload, $options);
        } else if ($method == 2) {
            $response = $this->curl->delete($location, null, $options);
        } else if ($method == 3) {
            $payload = json_encode($params, JSON_UNESCAPED_UNICODE);
            $response = $this->curl->put($location, $payload, $options);
        } else {
            $response = $this->curl->get($location, $params, $options);
        }

        if (!empty($this->curl->errno)) {
            return $this->curl->error;
        }
        return json_decode($response, false);
    }

    /**
     * Set custom profile field.
     * @param string $userid userid.
     * @param string $username username.
     * @return boolean Success or failure.
     */
    private function set_customprofile_username($userid, $username = null) {
        global $DB;
        if (empty($username)) {
            return false;
        }
        if (empty($this->config('sitebotusernamefield'))) {
            return false;
        }
        if ($field = $DB->get_record('user_info_field', ['shortname' => $this->config('sitebotusernamefield')])) {
            $record = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $field->id]);
            if ($record) {
                if ($record->data != $username) {
                    $record->data = $username;
                    $record->dataformat = 0;
                    $DB->update_record('user_info_data', $record);
                }
            } else {
                $newrecord = new \stdClass();
                $newrecord->userid = $userid;
                $newrecord->fieldid = $field->id;
                $newrecord->data = $username;
                $newrecord->dataformat = 0;
                $DB->insert_record('user_info_data', $newrecord);
            }
        }
        return true;
    }

    /**
     * Only if webHook has been created.
     * @param  string $chatid
     * @param  string $key
     * @param  string $username
     * @return boolean|string Success.
     */
    public function set_webhook_chatid($chatid = null, $key = null, $username = null) {
        global $DB;

        if (empty($this->config('sitebottoken')) || empty($chatid) || empty($key)) {
            return false;
        } else {
            $sql = "name = :name AND " . $DB->sql_compare_text('value') . " = :secret";
            $params = [
            'name'   => 'message_processor_max_chatid',
            'secret' => $this->secretprefix . $key,
            ];

            if ($record = $DB->get_record_select('user_preferences', $sql, $params, 'id, userid')) {
                $userid = $record->userid;
                if ($this->usersecret_match($key, $userid)) {
                    set_user_preference('message_processor_max_chatid', $chatid, $userid);
                    $this->set_customprofile_username($userid, $username);
                    $this->send_api_command(
                        'sendMessage',
                        [
                        'chat_id' => $chatid,
                        'text' => get_string('welcome', 'message_max'),
                        'reply_markup' => json_encode([
                        'keyboard' => [
                        ['/info', '/lang'],
                        ['/help'],
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false,
                        ]),
                        ]
                    );
                    $this->send_message('Use /help', $userid);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get userid by chatid.
     * @param  string $chatid
     * @return boolean|int $userids
     */
    public function get_userids_by_chatid($chatid) {
        global $DB;

        $userids = [];

        $sql = "name = :name AND " . $DB->sql_compare_text('value') . " = :secret";
        $params = [
            'name'   => 'message_processor_max_chatid',
            'secret' => $chatid,
            ];

        if ($records = $DB->get_records_select('user_preferences', $sql, $params, 'id, userid')) {
            foreach ($records as $record) {
                    $userids[] = $record->userid;
            }
        }

        return $userids;
    }
}
