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
 * MAX sender
 *
 * @package     message_max
 * @copyright   2025 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace message_max\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Default tasks.
 *
 * @package    paygw_robokassa
 * @copyright  2024 Alex Orlov <snicker@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class max_sender extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'message_max');
    }
    /**
     * Execute.
     */
    public function execute() {
        global $DB, $CFG;

        // Unfortunately this may take a long time, it should not be interrupted,
        // otherwise users get duplicate notification.
        \core_php_time_limit::raise();
        \raise_memory_limit(MEMORY_HUGE);

        $token = get_config('message_max', 'sitebottoken');
        $pmode = get_config('message_max', 'parsemode');

        $dir = $CFG->tempdir . '/max';

        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == '..' || $file == '.') {
                    continue;
                }

                $this->sendmsg($token, $pmode, $dir . '/' . $file);

                usleep(50000);
            }
            closedir($dh);
        } else {
            mkdir($dir);
        }
    }

    /**
     * Send.
     * @param string $token
     * @param string $pmode
     * @param string $file
     * @return boolean
     */
    private function sendmsg($token, $pmode, $file) {
        global $DB, $CFG;

        $chatid = '';
        $text   = '';

        $fh = fopen($file, "r+");
        if (flock($fh, LOCK_EX | LOCK_NB)) {
            $chatid = trim(fgets($fh));
            while (($buff = fgets($fh)) !== false) {
                $text .= $buff;
            }
        } else {
            mtrace($file . ' no file or locked');
            return true;
        }

        $this->curl = new \curl();

        $location = 'https://api.max.org/bot' . $token . '/sendMessage';

        $params = [
         'chat_id' => $chatid,
         'parse_mode' => $pmode,
         'text' => $text,
         'link_preview_options' => '{"is_disabled":true}',
        ];

        $options = [
         'CURLOPT_RETURNTRANSFER' => true,
         'CURLOPT_TIMEOUT' => 30,
         'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
         'CURLOPT_SSLVERSION' => CURL_SSLVERSION_TLSv1_2,
        ];

        $response = json_decode($this->curl->post($location, $params, $options));

        // Ckeck curl error.
        if (!empty($this->curl->errno)) {
            fclose($fh);
            mtrace($this->curl->error);
            return true;
        }

        // Check max error.
        if ($response->ok == true) {
            mtrace($response->result->message_id);
            unlink($file);
            fclose($fh);
            return false;
        } else {
            // Delete file and chatid if forbidden.
            if ($response->error_code == 403) {
                $DB->delete_records(
                    'user_preferences',
                    [ 'name' => 'message_processor_max_chatid', 'value' => $chatid]
                );
                unlink($file);
                fclose($fh);
                mtrace('delete forbidden ' . $chatid);
            }
            return true;
        }
    }
}
