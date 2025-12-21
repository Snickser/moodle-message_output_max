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
 * Plugin version and other meta-data are defined here.
 *
 * @package     message_max
 * @copyright   2025 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php'); // @codingStandardsIgnoreLine

require_once(__DIR__ . '/lib.php');

require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
use core_completion\progress;

\core\session\manager::init_empty_session();

$headers = getallheaders();

$update = file_get_contents("php://input");
$data = json_decode($update, false);

$config = get_config('message_max');

if ($config->maxwebhookdump) {
    file_put_contents($CFG->tempdir . '/max.log', print_r($data, true) . "\n\n", FILE_APPEND | LOCK_EX);
}

if (!isset($headers['X-Max-Bot-Api-Secret']) || $headers['X-Max-Bot-Api-Secret'] != $config->sitebotsecret) {
    http_response_code(200);
    echo "OK";
    die;
}

$langs = get_string_manager()->get_list_of_translations();

$tg = new message_max\manager();

$user = null;
$userid = null;

if (isset($data->user->name) && isset($data->payload) && isset($data->user_id)) {
    $userid = $data->user_id;
    $newuser = $tg->set_webhook_chatid($userid, $data->payload, $data->user->name);

    if (empty($user->{$config->sitebotphonefield}) && ($user || $newuser)) {
        $attachments = [
        [
        'type' => 'inline_keyboard',
        'payload' => [
            'buttons' => [
                [
                    [
                        'type' => 'request_contact',
                        'text' => get_string('provide', 'message_max'),
                    ],
                ],
            ],
        ],
        ],
        ];
        if ($user) {
            $text = get_string('welcometosite', 'moodle', ['firstname' => fullname($user)]);
        } else {
            $text = get_string('welcometosite', 'moodle', ['firstname' => $data->user->first_name]);
        }
        $text .= PHP_EOL . get_string('enter_phone', 'message_max');
    } else {
        $attachments = [
        [
        'type' => 'inline_keyboard',
        'payload' => [
            'buttons' => [
                [
                    [
                        'type' => 'message',
                        'text' => '/info',
                    ],
                    [
                        'type' => 'message',
                        'text' => '/lang',
                    ],
                ],
                [
                    [
                        'type' => 'message',
                        'text' => '/help',
                    ],
                ],
            ],
        ],
        ],
        ];
        if ($user) {
            $text = get_string('welcomeback', 'moodle', ['firstname' => fullname($user)]);
        } else {
            $text = get_string('welcometosite', 'moodle', ['firstname' => $data->user->first_name]);
        }
    }

    $response = $tg->send_api_command(
        'messages?user_id=' . $userid . '&disable_link_preview=true',
        [
         'text' => $text,
         'format' => 'html',
         'attachments' => $attachments,
        ],
        1
    );
    $response = $attachments;
} else if (isset($data->message) && !isset($data->callback)) {
    $fromid = clean_param($data->message->sender->user_id ?? null, PARAM_INT);
    $chatid = clean_param($data->message->sender->user_id ?? null, PARAM_INT);
    $text = clean_param($data->message->body->text ?? null, PARAM_TEXT);
    $username = clean_param($data->message->sender->name ?? null, PARAM_TEXT);

    $record = $DB->get_record('message_max', ['chatid' => $chatid]);
    $lastmsgid = clean_param($data->message->sender->mid ?? null, PARAM_TEXT);
    $lastdata = $text;
    $step = 'command';

    $userids = $tg->get_userids_by_chatid($fromid);
    if ($userids) {
        if (count($userids) > 1) {
            $userid = get_user_preferences('message_processor_max_prefid', $userids[0], $userids[0]);
        } else {
            $userid = $userids[0];
        }
        if ($user = $DB->get_record('user', ['id' => $userid])) {
            \core\session\manager::set_user($user);
            profile_load_data($user);
        }
    }

    if ($userid) {
        $lang = get_user_preferences('message_processor_max_lang', null, $userid);
        force_current_language($lang);
    }

    if ($chatid < 0) {
        if ($user) {
            max_private_answer($tg, $config->sitebotusername, $chatid, $data->message->message_id);
        } else {
            max_private_answer($tg, $config->sitebotusername, $chatid, $data->message->message_id, "?start");
        }
    }

    if ($userid && isset($data->message->body->attachments[0]->payload->vcf_info)) {
        if ($data->message->body->attachments[0]->payload->max_info->user_id == $fromid) {
            $vcf = $data->message->body->attachments[0]->payload->vcf_info;
            $line = preg_replace("/\r\n[ \t]/", '', $vcf);
            $phone = preg_replace('/\D+/', '', strstr($line, 'cell:'));

            if ($phone && ($config->sitebotphonefield == 'phone1' || $config->sitebotphonefield == 'phone2')) {
                $DB->set_field('user', $config->sitebotphonefield, $phone, ['id' => $userid]);
                $tg->send_message(get_string('thanks') . ' 🙂', $userid);
            } else if ($phone && $config->sitebotphonefield) {
                $shortname = preg_replace('/^profile_field_/', '', $config->sitebotphonefield);
                if ($shortname) {
                    $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
                    $existing = $DB->get_record('user_info_data', [
                    'userid'  => $userid,
                    'fieldid' => $field->id,
                    ]);
                    if ($existing) {
                        $existing->data = $phone;
                        $existing->dataformat = 0;
                        $DB->update_record('user_info_data', $existing);
                    } else {
                        $record = (object)[
                        'userid'     => $userid,
                        'fieldid'    => $field->id,
                        'data'       => $phone,
                        'dataformat' => 0,
                        ];
                        $DB->insert_record('user_info_data', $record);
                    }
                    $tg->send_message(get_string('thanks') . ' 🙂', $userid);
                }
            }
        } else {
            $tg->send_message('😕 ' . get_string('unknownuser'), $fromid);
        }
    } else if (strpos($text, '/pay') === 0 && $config->sitebotpay) {
        if (!$cost = (int)substr($text, 5)) {
            $numbers = array_map('trim', explode(',', $config->sitebotpaycosts));
            $buttons = array_map(function ($n) {
                return [
                'text' => $n,
                'callback_data' => '/pay ' . $n,
                ];
            }, $numbers);
            $keyboard = [
            'inline_keyboard' => [ $buttons,
            ],
            ];
            $params = [
            'chat_id' => $fromid,
            'text' => get_string('botpay', 'message_max', $config->sitebotpaycurrency),
            'reply_markup' => json_encode($keyboard),
            ];
            $response = $tg->send_api_command('sendMessage', $params);
        } else {
            $fromid = clean_param($data->message->from->id, PARAM_INT);
            $cost = $cost * 100;
            $response = $tg->send_api_command('sendInvoice', [
            "chat_id" => $fromid,
            "title" => get_string('botpaytitle', 'message_max'),
            "description" => get_string('botpaydesc', 'message_max'),
            "payload" => "Donate",
            "provider_token" => $config->sitebotpay,
            "currency" => $config->sitebotpaycurrency,
            "start_parameter" => "test",
            "prices" => json_encode([
            [
            "label"  => get_string('botpaydesc', 'message_max'),
            "amount" => $cost,
            ],
               ]),

            ]);
        }
    } else if (strpos($text, '/courses') === 0 && $userid) {
        $courses = get_courses(null, true);
        $list = '';
        foreach ($courses as $course) {
            if ($course->visible) {
                if (!$list) {
                    $buff = '🏰 ';
                } else {
                    $buff = '🔸 ';
                }
                $buff .= '<b>' . format_string($course->fullname, true) . '</b>' . PHP_EOL;
                if (!empty($course->summary) && mb_strlen($course->summary) + mb_strlen($buff) < 3980) {
                    $buff .= '<i>  ' . format_string($course->summary, false) . '</i>' . PHP_EOL;
                }
                if (!$list) {
                    $buff .= PHP_EOL;
                }
                if (mb_strlen($list) + mb_strlen($buff) < 3980) {
                    $list .= $buff;
                } else {
                    $tg->send_message($list, $userid);
                    $list = $buff;
                }
            }
        }
        $tg->send_message($list, $userid);
    } else if (strpos($text, '/help') === 0 && $userid) {
        $text = null;
        if ($userid) {
            $text = get_string('bothelp', 'message_max');
        } else {
            $text = get_string('bothelp_anonymous', 'message_max');
        }
        if (file_exists($CFG->dirroot . '/admin/tool/certificate/lib.php')) {
            $text .= PHP_EOL . get_string('botcertificates', 'message_max');
        }
        if (count($userids) > 1) {
            $text .= PHP_EOL . get_string('botuseridhelp', 'message_max');
        }

        $hasbotstudents = true;
        $courses = enrol_get_all_users_courses($userid, true, '*');
        $roleids = array_map('intval', explode(',', $config->sitebotmsgroles));
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            $hasrole = false;
            foreach ($roleids as $roleid) {
                if (user_has_role_assignment($userid, $roleid, $context->id)) {
                    $hasrole = true;
                    break;
                }
            }
            if (!$hasrole) {
                continue;
            }
            if ($config->sitebotenablereports && $hasbotstudents) {
                $text .= PHP_EOL . get_string('botstudents', 'message_max');
                $hasbotstudents = false;
            }
            $groups = groups_get_all_groups($course->id);
            foreach ($groups as $group) {
                $members = groups_get_members($group->id, 'u.id');
                if (isset($members[$userid])) {
                    $text .= PHP_EOL . get_string('botmessagehelp', 'message_max');
                    break;
                }
            }
        }

        if (!empty($config->sitebotpay)) {
            $text .= "\n/pay - " . get_string('botpaytitle', 'message_max');
        }

        $tg->send_message($text, $userid);
    } else if (strpos($text, '/info') === 0) {
        $params = [
            'text' => '<b>' . format_string($SITE->fullname) . '</b>' . "\n🌐 " . $CFG->wwwroot . "\n✉️ " . $CFG->supportemail .
            ($CFG->supportpage ? "\n🛠 " . $CFG->supportpage : '') .
            ($CFG->servicespage ? "\n⭐ " . $CFG->servicespage : ''),
            'format' => 'HTML',
            ];
            $response = $tg->send_api_command('messages?user_id=' . $chatid . '&disable_link_preview=true', $params, 1);
    } else if (strpos($text, '/faq') === 0) {
        $params = [
            'text' => get_string('botfaq', 'message_max') .
            ($CFG->supportpage ? "\n$CFG->supportpage" : null) . "\n\n" .
            format_string(get_string('botfaqtext', 'message_max'), true),
            'format' => 'HTML',
            ];
            $response = $tg->send_api_command('messages?user_id=' . $chatid . '&disable_link_preview=true', $params, 1);
    } else if (strpos($text, '/userid') === 0 && $userid) {
        $buttons = [];
        foreach ($userids as $id) {
            $user = $DB->get_record('user', ['id' => $id]);
            $buttons[] = [[
                'text' => fullname($user),
                'callback_data' => '/userid ' . $id,
            ]];
        }
        $keyboard = [
        'inline_keyboard' => $buttons,
        ];
        $params = [
        'chat_id' => $fromid,
        'text' => get_string('botuserid', 'message_max', $userid),
        'reply_markup' => json_encode($keyboard),
        ];
        $response = $tg->send_api_command('sendMessage', $params);
    } else if (strpos($text, '/progress') === 0 && $userid) {
        $courses = enrol_get_users_courses($userid);
        $buttons = [];
        foreach ($courses as $course) {
            $buttons[] = [[
                'text' => format_string($course->fullname),
                'payload' => '/progress ' . $course->id,
                'type' => 'callback',
            ]];
        }
        $keyboard = [
        'type' => 'inline_keyboard',
        'payload' => ['buttons' => $buttons],
        ];
        $response = $tg->send_api_command(
            'messages?user_id=' . $chatid,
            [
            'text' => '📊 ' . get_string('selectacourse'),
            'attachments' => [$keyboard],
            ],
            1,
        );
    } else if (strpos($text, '/students') === 0 && $userid && $config->sitebotenablereports) {
        $courses = enrol_get_users_courses($userid);
        $buttons = [];
        foreach ($courses as $course) {
            $buttons[] = [[
                'text' => format_string($course->fullname),
                'callback_data' => '/students ' . $course->id,
            ]];
        }
        $keyboard = [
        'inline_keyboard' => $buttons,
        ];
        $response = $tg->send_api_command(
            'sendMessage',
            [
            'chat_id' => $fromid,
            'text' => '📚 ' . get_string('selectacourse') . ($buttons ? null : "\n\n" . get_string('none')),
            'reply_markup' => json_encode($keyboard),
            ]
        );
    } else if (strpos($text, '/enrols') === 0 && $userid) {
        $courses = enrol_get_users_courses($userid);
        $text = '';
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            $completion = new completion_info($course);
            $progress = \core_completion\progress::get_course_progress_percentage($course, $userid) ?? 0;
            $url = $CFG->wwwroot . '/course/view.php?id=' . $course->id;
            $text .= PHP_EOL . '• ' . "<a href='{$url}'>" . format_string($course->fullname) . '</a>' .
            (floor($progress) ? ' (' . floor($progress) . '%)' : null);
        }
        if (!$courses) {
            $text = PHP_EOL . get_string('no') . PHP_EOL;
        }
        $tg->send_message(get_string('botenrols', 'message_max') . PHP_EOL . $text, $userid);
    } else if (strpos($text, '/events') === 0 && $userid) {
        $eventtype = ['user' => '📌', 'group' => '🔔', 'course' => '🎓'];
        $calendar = \calendar_information::create(time(), 0, 0);
        $view = calendar_get_view($calendar, 'upcoming');
        $events = $view[0]->events ?? [];
        $text = null;
        foreach ($events as $event) {
            $start = date('d.m.Y H:i', $event->timestart);
            $end = date('d.m.Y H:i', $event->timestart + $event->timeduration);
            $duration = $event->timeduration ? '(' . round($event->timeduration / 60) . ' мин)' : '';
            $text .= $eventtype[$event->eventtype] .
            " {$start} — <a href='{$event->viewurl}'>" . format_string($event->name) . "</a> {$duration}\n" .
            ($event->description ? ' ' . get_string('subject') . ': ' .
            mb_substr(trim(format_string($event->description)), 0, 100, 'UTF-8') . PHP_EOL : null);
        }
        $head = get_string('botevents', 'message_max');
        if ($text) {
            $text = $head . $text;
        } else {
            $text = $head . get_string('none');
        }

        $keyboard = [[
            'type' => 'inline_keyboard',
            'payload' => [
                'buttons' => [
                    [
            ['text' => '+ ' . get_string('newevent', 'calendar'), 'type' => 'callback', 'payload' => '/newevent'],
                    ],
                ],
            ],
        ]];
        $response = $tg->send_api_command(
            'messages?user_id=' . $chatid . '&disable_link_preview=true',
            [
            'text' => $text,
            'format' => 'HTML',
            'attachments' => $keyboard,
            ],
            1
        );
    } else if (strpos($text, '/lang') === 0 && $userid) {
        $buttons = [];
        foreach ($langs as $langcode => $name) {
            $buttons[] = [[
                'text' => $name,
                'payload' => '/lang ' . $langcode,
                'type' => 'callback',
            ]];
        }

        $keyboard = [[
        'type' => 'inline_keyboard',
        'payload' => ['buttons' => $buttons],
        ]];

        $params = [
            'text' => get_string(
                'botlang',
                'message_max',
                get_user_preferences('message_processor_max_lang', get_string('none'), $userid),
            ),
            'attachments' => $keyboard,
        ];
        $response = $tg->send_api_command('messages?user_id=' . $fromid . '&disable_link_preview=true', $params, 1);
        $response = $params;
    } else if (strpos($text, '/message') === 0 && $userid) {
        $courses = enrol_get_all_users_courses($userid, false, '*');
        $buttons = [];
        foreach ($courses as $course) {
            $buttons[] = [[
                'text' => format_string($course->fullname),
                'callback_data' => '/message ' . $course->id,
            ]];
        }
        $keyboard = [
        'inline_keyboard' => $buttons,
        ];
        $response = $tg->send_api_command(
            'sendMessage',
            [
            'chat_id' => $fromid,
            'text' => '📚 ' . get_string('selectacourse'),
            'reply_markup' => json_encode($keyboard),
            ]
        );
    } else if (strpos($text, '/certificates') === 0 && $userid) {
        $certs = max_get_user_certificates($userid);
        $text = get_string('botcerts', 'message_max');
        $buff = '';
        foreach ($certs as $cert) {
            $buff .= '• ' . "<a href='{$cert['url']}'>{$cert['name']}</a>" . ' — ' . $cert['date'] . PHP_EOL;
        }
        if (!$buff) {
            $text .= get_string('no');
        } else {
            $text .= $buff;
        }
        $keyboard = [
            'inline_keyboard' => [[[
            'text' => get_string('botcertdownload', 'message_max'),
            'callback_data' => '/getcert',
            ]]],
            ];
        $params = [
            'chat_id' => $fromid,
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => '{"is_disabled":true}',
            ];
        if ($buff) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        $response = $tg->send_api_command('sendMessage', $params);
    } else if (isset($data->message->successful_payment)) {
        http_response_code(200);
        echo "OK";
        die;
    } else if ($text && $userid && $record->laststep == 'get_name') {
        $keyboard = [
        'inline_keyboard' => [[
        [
            'text' => '✅  ' . get_string('apply'),
            'callback_data' => $record->lastdata . " {$text}",
        ],
        ]],
        ];
        $response = $tg->send_api_command(
            'sendMessage',
            [
            'chat_id' => $fromid,
            'text' => '🏷 ' . get_string('eventname', 'calendar') . ':' . PHP_EOL . $text,
            'reply_markup' => json_encode($keyboard),
            ]
        );
        if ($record->lastmsgid) {
            $tg->send_api_command(
                'deleteMessage',
                [
                'chat_id' => $fromid,
                'message_id' => $record->lastmsgid,
                ]
            );
        }

        $step = 'get_name';
        $lastmsgid = $response->result->message_id;
        $lastdata = $record->lastdata;
    } else if (isset($text) && $userid && $record->laststep == 'get_duration') {
        $timestamp = (int)$text;

        $keyboard = [
        'inline_keyboard' => [[
        [
            'text' => '✅  ' . get_string('apply'),
            'callback_data' => $record->lastdata . " {$timestamp}",
        ],
        ]],
        ];
        $response = $tg->send_api_command(
            'sendMessage',
            [
            'chat_id' => $fromid,
            'text' => '⏱️ ' . get_string('eventduration', 'calendar') . ' ' . $timestamp . ' ' . get_string("minutes"),
            'reply_markup' => json_encode($keyboard),
            ]
        );
        if ($record->lastmsgid) {
            $tg->send_api_command(
                'deleteMessage',
                [
                'chat_id' => $fromid,
                'message_id' => $record->lastmsgid,
                ]
            );
        }

        $step = 'get_duration';
        $lastmsgid = $response->result->message_id;
        $lastdata = $record->lastdata;
    } else if (isset($text) && $userid && $record->laststep == 'get_time') {
        $timestamp = strtotime($text);
        if ($timestamp < time()) {
            $timestamp = time() + 86400 * 2;
        }

        $keyboard = [
        'inline_keyboard' => [[
        [
            'text' => '✅ ' . get_string('apply'),
            'callback_data' => $record->lastdata . " {$timestamp}",
        ],
        ]],
        ];
        $response = $tg->send_api_command(
            'sendMessage',
            [
            'chat_id' => $fromid,
            'text' => '⏰ ' . userdate($timestamp),
            'reply_markup' => json_encode($keyboard),
            ]
        );

        if ($record->lastmsgid) {
            $tg->send_api_command(
                'deleteMessage',
                [
                'chat_id' => $fromid,
                'message_id' => $record->lastmsgid,
                ]
            );
        }

        $step = 'get_time';
        $lastmsgid = $response->result->message_id;
        $lastdata = $record->lastdata;
    } else if ($text && $userid && $record->laststep == 'get_text') {
        $keyboard = [
        'inline_keyboard' => [[
        [
            'text' => '✉️ ' . get_string('submit'),
            'callback_data' => $record->lastdata . ' 1',
        ],
        [
            'text' => '❌ ' . get_string('cancel'),
            'callback_data' => $record->lastdata . ' 0',
        ],
        ]],
        ];
        $response = $tg->send_api_command(
            'sendMessage',
            [
            'chat_id' => $fromid,
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => '{"is_disabled":true}',
            'reply_markup' => json_encode($keyboard),
            ]
        );

        if ($record->lastmsgid) {
            $tg->send_api_command(
                'deleteMessage',
                [
                'chat_id' => $fromid,
                'message_id' => $record->lastmsgid,
                ]
            );
        }

        $step = 'get_text';
        $lastmsgid = $response->result->message_id;
        $lastdata = $record->lastdata;
    } else if ($text && $userid) {
        $response = max_send_menu($tg, $fromid, get_string('botidontknow', 'message_max'));
    } else if ($text) {
        $tg->send_api_command(
            'sendMessage',
            [
            'chat_id' => $fromid,
            'text' => get_string('firstregister', 'message_max', $CFG->wwwroot),
            ]
        );
        http_response_code(200);
        echo "OK";
        die;
    }

    if ($record) {
        $record->lastmsgid    = $lastmsgid;
        $record->lastdata     = $lastdata;
        $record->laststep     = $step;
        $record->timemodified = time();
        $DB->update_record('message_max', $record);
    } else {
        $record = new stdClass();
        $record->chatid       = $chatid;
        $record->lastmsgid    = $lastmsgid;
        $record->lastdata     = $lastdata;
        $record->laststep     = $step;
        $record->timemodified = time();
        $DB->insert_record('message_max', $record);
    }
} else if (isset($data->callback->payload)) {
    $fromid = clean_param($data->callback->user->user_id, PARAM_INT);
    $chatid = $fromid;

    $record = $DB->get_record('message_max', ['chatid' => $chatid]);
    $lastmsgid = clean_param($data->message->body->mid, PARAM_TEXT);
    $lastdata = clean_param($data->callback->payload, PARAM_TEXT);
    $step = 'callback';

    $userids = $tg->get_userids_by_chatid($fromid);
    if ($userids) {
        if (count($userids) > 1) {
            $userid = get_user_preferences('message_processor_max_prefid', $userids[0], $userids[0]);
        } else {
            $userid = $userids[0];
        }
        if ($user = $DB->get_record('user', ['id' => $userid])) {
            \core\session\manager::set_user($user);
        }
    }

    if (strpos($data->callback->payload, '/pay') === 0 && $cost = substr($data->callback->payload, 5)) {
        $fromid = clean_param($data->callback_query->from->id, PARAM_INT);
        $cost = $cost * 100;
        $response = $tg->send_api_command('sendInvoice', [
        "chat_id" => $fromid,
        "title" => get_string('botpaytitle', 'message_max'),
        "description" => get_string('botpaydesc', 'message_max'),
        "payload" => "Donate",
        "provider_token" => $config->sitebotpay,
        "currency" => $config->sitebotpaycurrency,
        "start_parameter" => "test",
        "prices" => json_encode([
        [
            "label"  => get_string('botpaydesc', 'message_max'),
            "amount" => $cost,
        ],
           ]),

        ]);
    } else if (strpos($data->callback->payload, '/lang') === 0 && $lang = substr($data->callback->payload, 6)) {
        $languages = [
        'ru' => ['flag' => '🇷🇺'],
        'en' => ['flag' => '🇺🇸'],
        'be' => ['flag' => '🇧🇾'],
        'uk' => ['flag' => '🇺🇦'],
        ];

        if (count($userids) > 1) {
            $userid = get_user_preferences('message_processor_max_prefid', $userids[0], $userids[0]);
        }
        if ($userid) {
            set_user_preference('message_processor_max_lang', $lang, $userid);
            $tg->send_message(($languages[$lang]['flag'] ?? 'Ⓜ️'), $userid);
            $user = new stdClass();
            $user->id = $userid;
            $user->lang = $lang;
            user_update_user($user, false, true);
        }
    } else if (strpos($data->callback_query->data, '/students') === 0 && $userid && $config->sitebotenablereports) {
        preg_match('/^\/students(?: (\d+))?(?: (-?\d+))?(?: (\d+))?/', $data->callback_query->data, $matches);
        $courseid = isset($matches[1]) ? (int)$matches[1] : null;
        $groupid  = isset($matches[2]) ? (int)$matches[2] : null;
        $accept   = isset($matches[3]) ? (int)$matches[3] : null;

        if (!$config->sitebotwarnreport) {
            $accept = 1;
        }

        if ($courseid) {
            $tg->send_api_command(
                'deleteMessage',
                [
                'chat_id' => $fromid,
                'message_id' => $data->callback_query->message->message_id,
                ]
            );
        }

        $page = '🎓 ' . get_string('students') . PHP_EOL . PHP_EOL;
        $context = context_course::instance($courseid);

        $hasrole = false;
        foreach (explode(',', $config->sitebotmsgroles) as $roleid) {
            if (user_has_role_assignment($userid, $roleid, $context->id)) {
                $hasrole = true;
                break;
            }
        }

        if (has_capability('moodle/course:viewparticipants', $context, $userid) && $hasrole) {
            if ($courseid && $groupid !== null && $accept == 1) {
                $step = 'done';
                $num = 1;
                $students = get_enrolled_users($context, false, $groupid, '*');
                foreach ($students as $student) {
                    $page .= $num++ . '. ';
                    profile_load_custom_fields($student);
                    $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
                    'userid' => $student->id,
                    'courseid' => $courseid,
                    ]);
                    $course = get_course($courseid);
                    $completion = new completion_info($course);
                    $progress = \core_completion\progress::get_course_progress_percentage($course, $student->id) ?? 0;
                    $progress = round($progress, 1);

                    $instances = enrol_get_instances($courseid, true);
                    $text = null;
                    foreach ($instances as $instance) {
                        $userenrol = $DB->get_record('user_enrolments', [
                            'enrolid' => $instance->id,
                            'userid' => $student->id,
                        ]);

                        $now = time();
                        if ($userenrol) {
                            if ($userenrol->status == ENROL_USER_SUSPENDED) {
                                $text .= '⛔';
                            } else if ($userenrol->timestart > $now) {
                                    $text .= '🅿️';
                            } else if ($userenrol->timeend != 0 && $userenrol->timeend <= $now) {
                                $text .= '⏰';
                            } else if (time() - $lastaccess > 86400 * 7) {
                                $text .= '🟡';
                            } else {
                                $text .= '🟢';
                            }
                        }
                    }

                    $text .= ' ' . fullname($student, true);
                    if ($config->sitebotusernamefield && $student->profile[$config->sitebotusernamefield]) {
                        $text .= ' @' . $student->profile[$config->sitebotusernamefield];
                    }

                    foreach (explode(',', $config->sitebotreportfields) as $field) {
                        $student->{$field} ? $text .= ' | ' . format_string($student->{$field}) : null;
                        $student->profile[$field] ? $text .= ' | ' . format_string($student->profile[$field]) : null;
                    }

                    $text .= ' | ' .
                    ($lastaccess ? userdate($lastaccess, '%d.%m.%Y %H:%M') : get_string('never')) .
                    ($progress ? " - {$progress}%" : null) .
                    PHP_EOL;

                    if (mb_strlen($text) + mb_strlen($page) < 4093) {
                        $page .= $text;
                    } else {
                        $page .= '...';
                        $tg->send_message($page, $userid);
                        $page = $text;
                    }
                }
                $tg->send_message($page, $userid);
            } else if (!$groupid && $courseid) {
                $step = 'getgroup';
                $groups = groups_get_all_groups($courseid, $userid);
                foreach ($groups as $group) {
                        $keyboard['inline_keyboard'][] = [[
                        'text' => format_string($group->name . ($group->description ? " - {$group->description}" : null)),
                        'callback_data' => "/students {$courseid} {$group->id}",
                        ]];
                }
                if (has_capability('moodle/site:accessallgroups', $context, $userid)) {
                    $keyboard['inline_keyboard'][] = [[
                    'text' => '✖️ ' . get_string('groupsnone'),
                    'callback_data' => "/students {$courseid} -1",
                    ]];
                    $keyboard['inline_keyboard'][] = [[
                    'text' => '✳️ ' . get_string('allparticipants'),
                    'callback_data' => "/students {$courseid} 0",
                    ]];
                }
                $response = $tg->send_api_command(
                    'sendMessage',
                    [
                    'chat_id' => $fromid,
                    'text' => '📖 ' . get_string('selectagroup'),
                    'reply_markup' => json_encode($keyboard),
                    ]
                );
            } else if ($accept === 0) {
                $step = 'cancel';
            } else {
                $step = 'accept';
                $keyboard = [
                'inline_keyboard' => [[
                [
                'text' => '⚠️ ' . get_string('policyaccept'),
                'callback_data' => "/students {$courseid} {$groupid} 1",
                ],
                [
                'text' => '❌ ' . get_string('cancel'),
                'callback_data' => "/students {$courseid} {$groupid} 0",
                ],
                ]],
                ];
                $response = $tg->send_api_command(
                    'sendMessage',
                    [
                    'chat_id' => $fromid,
                    'text' => '⁉️ ' . format_string(get_string('reportenabler_desc1', 'message_max')),
                    'reply_markup' => json_encode($keyboard),
                    ]
                );
            }
        } else {
            $page = '💁🏻 ' . get_string('none');
            $tg->send_message($page, $userid);
        }
    } else if (strpos($data->callback->payload, '/progress') === 0 && $userid) {
        $progress = [
        '⬜️',
        '🟩',
        '🟥',
        '🟥',
        ];

        $courseid = (int)substr($data->callback->payload, 10);
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($courseid);
        $info   = new completion_info($course);
        $modinfo = get_fast_modinfo($course, $userid);

        $text = '🎓 <b>' . format_string($course->fullname) . "</b>\n\n";

        $completed = 0;
        $total = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (!$info->is_enabled($cm)) {
                continue;
            }
            $total++;
            $data = $info->get_data($cm, false, $userid);
            $state = (int)$data->completionstate;
            if ($state > 0) {
                $completed++;
            }

            $text .= $progress[$state] . ' • ' . format_string($cm->name) . PHP_EOL;
        }

        $percentage = $total ? round(($completed / $total) * 100, 1) : 0;
        $text .= "\n📈 " . get_string('progress') . ': ' . round($percentage, 1) . "%";

        $tg->send_message($text, $userid);
    } else if (strpos($data->callback->payload, '/newevent') === 0 && $userid) {
        preg_match(
            '/^\/newevent(?:\s+(\d+))?(?:\s+(\d+))?(?:\s+(\d+))?(?:\s+(\d+))?(?:\s+(\d+))?(?:\s+(.+))?$/u',
            $data->callback->payload,
            $matches
        );

        $types = [0 => 'user', 1 => 'course', 2 => 'group'];

        $type     = isset($matches[1]) ? (int)$matches[1] : null;
        $courseid = isset($matches[2]) ? (int)$matches[2] : null;
        $groupid  = isset($matches[3]) ? (int)$matches[3] : null;
        $time     = isset($matches[4]) ? (int)$matches[4] : null;
        $duration = isset($matches[5]) ? (int)$matches[5] : null;
        $name     = isset($matches[6]) ? trim($matches[6]) : null;


        $hascourse = false;
        $hasgroup = false;

        if ($type === null) {
            $step = 'type';
            $params['text'] = '💁🏻 ' . get_string('select') . ' ' . get_string('eventtype', 'calendar');
            $courses = enrol_get_users_courses($userid);
            if ($courses) {
                foreach ($courses as $course) {
                    $context = context_course::instance($course->id);
                    if (has_capability('moodle/calendar:manageentries', $context, $userid)) {
                        $hascourse = true;
                    }
                    if (has_capability('moodle/calendar:managegroupentries', $context, $userid)) {
                        $hasgroup = true;
                    }
                }
            }

            $buttons = [
            [
            'text' => get_string('personal'),
            'type' => 'callback',
            'payload' => '/newevent 0 0 0',
            ],
            [
            'text' => get_string('course'),
            'type' => 'callback',
            'payload' => '/newevent 1',
            ],
            ];

            if ($hasgroup) {
                $buttons[] = [
                'text' => get_string('group'),
                'type' => 'callback',
                'payload' => '/newevent 2',
                ];
                $keyboard['payload']['buttons'] = [$buttons];
            } else if ($hascourse) {
                $keyboard['payload']['buttons'] = [$buttons];
            } else {
                $step = 'get_time';
                $params['text'] = '⏰ ' . get_string('enter', 'message_max') . ' ' .
                get_string(
                    'and',
                    'moodle',
                    ['one' => get_string('eventdate', 'calendar'), 'two' => get_string('eventstarttime', 'calendar')]
                ) .
                PHP_EOL . get_string('enter_time', 'message_max');
                $lastdata = '/newevent 0 0 0';
                $lastmsgid = 0;
            }
        } else if ($courseid === null && $type) {
            $step = 'course';
            $params['text'] = '📚 ' . get_string('selectacourse');
            $courses = enrol_get_users_courses($userid);
            foreach ($courses as $course) {
                $context = context_course::instance($course->id);
                if (has_capability('moodle/calendar:manageentries', $context, $userid)) {
                    $keyboard['payload']['buttons'][] = [[
                    'text' => format_string($course->fullname),
                    'type' => 'callback',
                    'payload' => "/newevent {$type} " . $course->id,
                    ]];
                }
            }
        } else if ($groupid === null && $type == 2 && $courseid) {
            $step = 'group';
            $params['text'] = '📖 ' . get_string('selectagroup');
            $context = context_course::instance($courseid);
            $groups = groups_get_all_groups($courseid, $userid);
            foreach ($groups as $group) {
                $keyboard['payload']['buttons'][] = [[
                'text' => format_string($group->name . ($group->description ? " - {$group->description}" : null)),
                'type' => 'callback',
                'payload' => "/newevent 2 {$courseid} " . $group->id,
                ]];
            }
        } else if ($time === null) {
            $step = 'get_time';
            $params['text'] = '⏰ ' . get_string('enter', 'message_max') . ' ' .
            get_string(
                'and',
                'moodle',
                ['one' => get_string('eventdate', 'calendar'), 'two' => get_string('eventstarttime', 'calendar')]
            ) .
            PHP_EOL . get_string('enter_time', 'message_max');
            $lastmsgid = 0;
            if ($groupid === null) {
                $lastdata = "/newevent {$type} {$courseid} 0";
            }
        } else if ($duration === null) {
            $step = 'get_duration';
            $params['text'] = '⏱️ ' . get_string('enter', 'message_max') . ' ' . get_string('durationminutes', 'calendar');
        } else if ($name === null) {
            $step = 'get_name';
            $params['text'] = '✏️ ' . get_string('enter', 'message_max') . ' ' . get_string('eventname', 'calendar');
        } else {
            if ($courseid) {
                $context = context_course::instance($courseid);
                if (!has_capability('moodle/calendar:manageentries', $context, $userid)) {
                    $courseid = false;
                    $groupid = false;
                    $type = 0;
                }
                if (!has_capability('moodle/calendar:managegroupentries', $context, $userid)) {
                    $groupid = false;
                    $type = 0;
                }
            }
            $event = new stdClass();
            $event->name        = $name;
            $event->description = '';
            $event->format      = FORMAT_HTML;
            $event->courseid    = $courseid ? $courseid : '';
            $event->groupid     = $groupid ? $groupid : '';
            $event->userid      = $userid;
            $event->modulename  = '';
            $event->instance    = 0;
            $event->eventtype   = $types[$type] ?? 'user';
            $event->timestart   = $time;
            $event->timeduration = $duration * 60;
            $calendarevent = calendar_event::create($event);
            if ($calendarevent) {
                $params['text'] = '✅ ' . get_string('eventcalendareventcreated', 'calendar');
            } else {
                $params['text'] = '❌ ' . get_string('erroraddingevent', 'calendar');
            }
            $step = "done";
        }

        if (isset($keyboard)) {
            $keyboard['type'] = 'inline_keyboard';
            $params['attachments'] = [$keyboard];
        }
        $response = $tg->send_api_command('messages?user_id=' . $fromid, $params, 1);
        $response = $params;
    } else if (strpos($data->callback_query->data, '/message') === 0 && $userid) {
        preg_match('/^\/message(?: (\d+))?(?: (\d+))?(?: (\d+))?/', $data->callback_query->data, $matches);
        $courseid = isset($matches[1]) ? (int)$matches[1] : null;
        $groupid  = isset($matches[2]) ? (int)$matches[2] : null;
        $submit   = isset($matches[3]) ? (int)$matches[3] : null;

        $keyboard = ['inline_keyboard' => []];

        $notify = false;

        $params = [
            'chat_id' => $chatid,
        ];

        if ($submit === 0) {
            $step = 'cancel';
            $params['text'] = '❎ ' . get_string('cancelled');
        } else if ($submit === 1) {
            $step = 'done';
            $params['text'] = '✅ ' . get_string('sent');
            $notify = true;
        } else if ($groupid === 0 || !empty($groupid)) {
            $params['text'] = get_string('botentertext', 'message_max');
            $lastmsgid = null;
            $step = 'get_text';
        } else if ($courseid) {
            $context = context_course::instance($courseid);
            $groups = groups_get_all_groups($courseid, $userid);
            $hasrole = false;
            foreach (explode(',', $config->sitebotmsgroles) as $roleid) {
                if (user_has_role_assignment($userid, $roleid, $context->id)) {
                    $hasrole = true;
                    break;
                }
            }
            if ($hasrole) {
                foreach ($groups as $group) {
                    $keyboard['inline_keyboard'][] = [[
                    'text' => format_string($group->name . ($group->description ? " - {$group->description}" : null)),
                    'callback_data' => "/message {$courseid} {$group->id}",
                    ]];
                }
                if (!$groups) {
                    $keyboard['inline_keyboard'][] = [[
                    'text' => get_string('botmsgall', 'message_max'),
                    'callback_data' => "/message {$courseid} 0",
                    ]];
                }
                $params['text'] = '📖 ' . get_string('selectagroup');
                $params['reply_markup'] = json_encode($keyboard);
            } else {
                $params['text'] = '💁🏻 ' . get_string('none');
            }
        }

        $params['message_id'] = $data->callback_query->message->message_id;
        $response = $tg->send_api_command('editMessageText', $params);
        if ($notify) {
            max_notify_users($courseid, $groupid, $userid, $data->callback_query->message->text);
        }
    } else if (strpos($data->callback_query->data, '/getcert') === 0 && $userid) {
        $certs = max_get_user_certificates($userid);
        if ($id = substr($data->callback_query->data, 9)) {
            $issue = \tool_certificate\template::get_issue_from_code($id);
            $context = \context_course::instance($issue->courseid, IGNORE_MISSING) ?: null;
            $template = $issue ? \tool_certificate\template::instance($issue->templateid) : null;
            if (
                $template && (\tool_certificate\permission::can_verify() ||
                \tool_certificate\permission::can_view_issue($template, $issue, $context))
            ) {
                $certurl = $template->get_issue_file($issue);
                $response = $tg->send_api_command('sendDocument', [
                    'chat_id' => $chatid,
                    'document' => $certurl,
                    'caption' => get_string('botcertyour', 'message_max'),
                ]);
            }
        } else {
            $keyboard = ['inline_keyboard' => []];
            foreach ($certs as $cert) {
                $keyboard['inline_keyboard'][] = [
                ['text' => $cert['name'] . ' - ' . $cert['date'], 'callback_data' => '/getcert ' . $cert['code']],
                ];
            }

            $response = $tg->send_api_command('editMessageText', [
            'chat_id' => $chatid,
            'message_id' => $data->callback_query->message->message_id,
            'text' => get_string('botcertselect', 'message_max'),
            'reply_markup' => json_encode($keyboard),
            ]);
        }
    } else if (strpos($data->callback_query->data, '/userid') === 0 && $id = substr($data->callback_query->data, 8)) {
        $userid = $userids[0];
        $uid = clean_param($id, PARAM_INT);
        if ($userid && $uid) {
            set_user_preference('message_processor_max_prefid', $uid, $userid);
            $response = $tg->send_api_command(
                'sendMessage',
                [
                'chat_id' => $fromid,
                'text' => '✅ ' . get_string('bulkselection', 'core', '🆔 ' . $uid),
                ]
            );
        }
    }

    if ($record) {
        $record->lastmsgid    = $lastmsgid;
        $record->lastdata     = $lastdata;
        $record->laststep     = $step;
        $record->timemodified = time();
        $DB->update_record('message_max', $record);
    } else {
        $record = new stdClass();
        $record->chatid       = $chatid;
        $record->lastmsgid    = $lastmsgid;
        $record->lastdata     = $lastdata;
        $record->laststep     = $step;
        $record->timemodified = time();
        $DB->insert_record('message_max', $record);
    }
}

if ($config->maxwebhookdump) {
    file_put_contents($CFG->tempdir . '/max.log', (!empty($response) ? print_r($response, true) : serialize($data)) .
    "\n\n", FILE_APPEND | LOCK_EX);
}
if ($fromid && isset($response->error_code)) {
     $tg->send_api_command(
         'sendMessage',
         [
            'chat_id' => $fromid,
            'text' => serialize($response->description),
         ]
     );
}

http_response_code(200);
echo "OK";
die;
