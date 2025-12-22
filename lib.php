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
 * @copyright   2025 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds navigation items to user profile.
 *
 * @param stdClass $navigation
 * @param stdClass $user The user object
 * @param stdClass $context
 */
function message_max_extend_navigation_user_settings($navigation, $user, $context) {
    global $USER;

    if ($USER->id !== $user->id) {
        return;
    }

    $manager = new \message_max\manager();
    $chatid = $manager->is_chatid_set($USER->id);

    $url = new moodle_url('/message/notificationpreferences.php');

    if ($chatid) {
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
 * Отправляет ответ в приватный чат пользователю или уведомление в группе.
 *
 * @param object $tg        Экземпляр клиента MAX API с методом send_api_command.
 * @param string $botname   Имя бота в MAX (без @).
 * @param int    $chatid    Идентификатор чата, куда отправляется сообщение.
 * @param int    $messageid ID сообщения, на которое будет дан reply.
 * @param string|null $start Дополнительный параметр (обычно payload для deep link).
 *
 * @return mixed Результат выполнения метода send_api_command (ответ MAX API).
 */
function message_max_private_answer($tg, $botname, $chatid, $messageid, $start = null) {
    if ($start) {
        $text = get_string('botanswer1', 'message_max');
    } else {
        $text = get_string('botanswer2', 'message_max');
    }

    $replymarkup = [
        'inline_keyboard' => [
            [
                [
                    'text' => get_string('proceed'),
                    'url' => "https://t.me/$botname$start",
                ],
            ],
        ],
    ];

    $options = [
        'text' => $text,
    ];
    return $tg->send_api_command(
        'messages?user_id=' . $chatid,
        $options,
        1
    );
}

/**
 * Получает список сертификатов пользователя.
 *
 * @param int $userid Идентификатор пользователя в Moodle
 * @return array Массив сертификатов пользователя
 */
function message_max_get_user_certificates(int $userid) {
    global $DB, $CFG;

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
 * Рассылает сообщение всем студентам указанной группы курса через систему сообщений Moodle.
 *
 * @param int    $courseid ID курса, к которому относится группа.
 * @param int    $groupid  ID группы внутри курса.
 * @param int    $userid   ID пользователя, от имени которого отправляется сообщение.
 * @param string $text     Текст сообщения.
 *
 * @return bool Возвращает true после успешного добавления сообщений в очередь.
 */
function message_max_notify_users(int $courseid, int $groupid, int $userid, $text) {
    global $DB, $CFG;

    $from = $DB->get_record('user', ['id' => $userid], '*');

    require_once($CFG->dirroot . '/group/lib.php');
    require_once($CFG->dirroot . '/course/lib.php');
    if ($groupid > 0) {
        $users = groups_get_members($groupid, 'u.*');
    } else if ($groupid == 0) {
        $context = context_course::instance($courseid);
        $users = get_enrolled_users($context);
    } else {
        return false;
    }
    $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*');
    foreach ($users as $to) {
        if (!user_has_role_assignment($to->id, $studentrole->id, context_course::instance($courseid)->id)) {
            continue;
        }
        if ($to->id == $from->id) {
            continue;
        }
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
 * Добавляет меню.
 *
 * @param object $tg        Экземпляр клиента MAX API с методом send_api_command.
 * @param int    $chatid    Идентификатор чата, куда отправляется сообщение.
 * @param string $text      Текст сообщения.
 * @return string Возвращает строку.
 */
function message_max_send_menu($tg, $chatid, $text) {
    $response = $tg->send_api_command(
        'messages?user_id=' . $chatid,
        [
        'text' => $text,
        ],
        1
    );
    return $response;
}
