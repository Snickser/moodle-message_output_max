<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * MAX message plugin version information.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a navigation node to the user's profile page if MAX is not connected.
 *
 * @param core_user\output\myprofile\tree $tree The myprofile tree object
 * @param stdClass $user The user object
 * @param bool $iscurrentuser Whether the user is the current user
 * @param stdClass $course The course object
 */
function message_max_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $USER;

    // Only show for the current user's profile.
    if ($USER->id !== $user->id) {
        return;
    }

    $botname = get_config('message_max', 'sitebotname');
    if (empty($botname)) {
        return;
    }

    $manager = new \message_max\manager();
    $chatid = $manager->is_chatid_set($USER->id);

    // If already connected, do nothing.
    if ($chatid) {
        return;
    } else {
        $msg = get_string('connectmemenu', 'message_max');
    }

    $url = new moodle_url('/message/notificationpreferences.php');
    $category = new core_user\output\myprofile\category('max', get_string('pluginname', 'message_max'), null);
    $node = new core_user\output\myprofile\node(
        'max',
        'message_max',
        $msg,
        null,
        $url
    );
    $tree->add_category($category);
    $tree->add_node($node);
}

/**
 * Adds navigation items to user settings page.
 * Shows connection status and link to connect MAX account.
 *
 * @param stdClass $navigation The navigation node
 * @param stdClass $user The user object
 * @param stdClass $context The user context
 */
function message_max_extend_navigation_user_settings($navigation, $user, $context) {
    global $USER;

    // Only show for the current user's settings.
    if ($USER->id !== $user->id) {
        return;
    }

    $manager = new \message_max\manager();
    $chatid = $manager->is_chatid_set($USER->id);

    $url = new moodle_url('/message/notificationpreferences.php');

    if ($chatid) {
        // User is already connected.
        $navigation->add(
            get_string('alreadyconnected', 'message_max'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'max_connected'
        );
    } else {
        $botname = get_config('message_max', 'sitebotname');
        if (empty($botname)) {
            return;
        }

        // Generate connection URL with user secret for webhook mode.
        if ($manager->config('webhook')) {
            $key = $manager->set_usersecret($USER->id);
            $url = 'https://t.me/' . $manager->config('sitebotusername') . '?start=' . $key;
        }

        $navigation->add(
            get_string('connectmemenu', 'message_max'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'max_disconnected'
        );
    }
}

/**
 * Sends a reply to a user in private chat or a notification in a group.
 * Displays a button to continue the conversation in the bot.
 *
 * @param object $mx        MAX API client instance with send_api_command method.
 * @param string $botname   Bot name in MAX (without @).
 * @param int    $chatid    Chat ID to send the message to.
 * @param int    $messageid Message ID to reply to.
 * @param string|null $start Optional deep link payload parameter.
 *
 * @return mixed MAX API response from send_api_command.
 */
function message_max_private_answer($mx, $botname, $chatid, $messageid, $start = null) {
    // Select message text based on whether user is starting fresh or continuing.
    if ($start) {
        $text = get_string('botanswer1', 'message_max');
    } else {
        $text = get_string('botanswer2', 'message_max');
    }

    // Build inline keyboard with link to bot.
    $replymarkup = [
        'inline_keyboard' => [
            [
                [
                    'text' => get_string('proceed'),
                    'url' => 'https://t.me/' . $botname . $start,
                ],
            ],
        ],
    ];

    $options = [
        'text' => $text,
    ];
    return $mx->send_api_command(
        'messages?user_id=' . $chatid,
        $options,
        1
    );
}

/**
 * Retrieves all certificates issued to a user.
 *
 * @param int $userid Moodle user ID.
 * @return array Array of certificates with name, date, code, and URL.
 */
function message_max_get_user_certificates(int $userid) {
    global $DB, $CFG;

    // Fetch certificate issues from database.
    $sql = "SELECT ci.id, ci.timecreated, ci.code, t.name
              FROM {tool_certificate_issues} ci
              JOIN {tool_certificate_templates} t ON t.id = ci.templateid
             WHERE ci.userid = :userid
          ORDER BY ci.timecreated DESC";
    $records = $DB->get_records_sql($sql, ['userid' => $userid]);

    $certs = [];
    foreach ($records as $rec) {
        $date = date('d.m.Y', $rec->timecreated);
        $url = $CFG->wwwroot . '/admin/tool/certificate/view.php?code=' . $rec->code;
        $certs[] = [
            'name' => $rec->name,
            'date' => $date,
            'code' => $rec->code,
            'url'  => $url,
        ];
    }
    return $certs;
}

/**
 * Sends a message to all students in a course group via Moodle messaging system.
 *
 * @param int    $courseid ID of the course containing the group.
 * @param int    $groupid  ID of the group (0 = all participants, -1 = all groups).
 * @param int    $userid   ID of the user sending the message.
 * @param string $text     Message text to send.
 *
 * @return bool True after successfully queuing messages.
 */
function message_max_notify_users(int $courseid, int $groupid, int $userid, $text) {
    global $DB, $CFG;

    $from = $DB->get_record('user', ['id' => $userid], '*');

    require_once($CFG->dirroot . '/group/lib.php');
    require_once($CFG->dirroot . '/course/lib.php');

    // Get users based on group ID.
    if ($groupid > 0) {
        $users = groups_get_members($groupid, 'u.*');
    } else if ($groupid == 0) {
        // All participants in the course.
        $context = context_course::instance($courseid);
        $users = get_enrolled_users($context);
    } else {
        return false;
    }

    $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*');
    foreach ($users as $to) {
        // Skip users without student role in this course.
        if (!user_has_role_assignment($to->id, $studentrole->id, context_course::instance($courseid)->id)) {
            continue;
        }
        // Skip sending to self.
        if ($to->id == $from->id) {
            continue;
        }
        // Create and send Moodle message.
        $eventdata = new \core\message\message();
        $eventdata->component         = 'moodle';
        $eventdata->name              = 'instantmessage';
        $eventdata->userfrom          = $from;
        $eventdata->userto            = $to;
        $eventdata->fullmessage       = $text;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->notification      = 0;
        $eventdata->courseid          = $courseid ?? 0;
        message_send($eventdata);
    }
    return true;
}

/**
 * Sends a simple text message via MAX API.
 *
 * @param object $mx     MAX API client instance with send_api_command method.
 * @param int    $chatid Chat ID to send the message to.
 * @param string $text   Message text to send.
 * @return mixed MAX API response.
 */
function message_max_send_menu($mx, $chatid, $text) {
    $response = $mx->send_api_command(
        'messages?user_id=' . $chatid,
        [
        'text' => $text,
        ],
        1
    );
    return $response;
}
