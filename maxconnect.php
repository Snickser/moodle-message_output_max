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
 * MAX connection handler.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/lib/filelib.php');

$action = optional_param('action', 'removechatid', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/message/output/max/maxconnect.php'));
$PAGE->set_context(context_system::instance());

require_login();
require_sesskey();

$maxmanager = new message_max\manager();

if ($action == 'setwebhook') {
    require_capability('moodle/site:config', context_system::instance());
    if (strpos($CFG->wwwroot, 'https:') !== 0) {
        $message = get_string('requirehttps', 'message_max');
    } else {
        if (empty(get_config('message_max', 'webhook'))) {
            $message = $maxmanager->set_webhook();
        } else {
            $message = $maxmanager->remove_webhook();
        }
    }
    redirect(new moodle_url('/admin/settings.php', ['section' => 'messagesettingmax']), $message);
} else if ($action == 'removechatid') {
    $userid = optional_param('userid', 0, PARAM_INT);
    if ($userid != 0) {
        $message = $maxmanager->remove_chatid($userid);
    }
    redirect(new moodle_url('/message/notificationpreferences.php', ['userid' => $userid]), $message);
}
