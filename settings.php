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

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'message_max_head',
        '',
        '',
    ));

    $maxmanager = new message_max\manager();

    $sitebottoken = $maxmanager->config('sitebottoken');
    $sitebotsecret = $maxmanager->config('sitebotsecret');
    $botname = $maxmanager->config('sitebotname');
    $botusername = $maxmanager->config('sitebotusername');

    if (empty($sitebotsecret)) {
        $sitebotsecret = bin2hex(random_bytes(32));
        set_config('sitebotsecret', $sitebotsecret, 'message_max');
    }

    if (!empty($sitebottoken)) {
        $maxmanager->update_bot_info();
    }

    $maxmanager = new message_max\manager();
    if (empty($sitebottoken)) {
        $site = get_site();
        $uniquename = $site->fullname . ' ' . get_string('notifications');
        $sitehostname = parse_url($CFG->wwwroot, PHP_URL_HOST);
        $parts = explode('.', $sitehostname);
        $botusername = $parts[0];

        // The username cannot be longer than 32 characters total, and must end in "bot".
        $botusername = substr($botusername, 0, 29) . 'Bot';

        $url = 'https://dev.max.ru/docs/chatbots/bots-create';
        $link = '<p><a href="' . $url . '" target="_blank">' . $url . '</a></p>';
        $a = new stdClass();
        $a->name = $uniquename;
        $a->username = $botusername;
        $text = get_string('setupinstructions', 'message_max', $a);
        $settings->add(new admin_setting_heading('setupmax', '', $text . $link));
    }

    $settings->add(new admin_setting_configtext(
        'message_max/sitebottoken',
        get_string('sitebottoken', 'message_max'),
        get_string('configsitebottoken', 'message_max'),
        null,
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'message_max/sitebotname',
        get_string('sitebotname', 'message_max'),
        get_string('configsitebotname', 'message_max'),
        null,
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'message_max/sitebotusername',
        get_string('sitebotusername', 'message_max'),
        get_string('configsitebotusername', 'message_max'),
        null,
        PARAM_TEXT
    ));

    $options = [
        '' => get_string('no'),
    ];
    $fields = profile_get_custom_fields();
    foreach ($fields as $f) {
        $options[$f->shortname] = format_string($f->name);
    }
    $settings->add(new admin_setting_configselect(
        'message_max/sitebotusernamefield',
        get_string('usernamefield', 'message_max'),
        get_string('customfield', 'message_max'),
        '',
        $options
    ));

    $options = [
        '' => get_string('no'),
    ];
    $chats = $maxmanager->send_api_command('chats');
    foreach ($chats->chats as $key => $value) {
        if ($value->status == 'active') {
            $options[$value->chat_id] = $value->title;
        }
    }
    $settings->add(new admin_setting_configmultiselect(
        'message_max/sitebotaddtogroup',
        get_string('sitebotaddtogroup', 'message_max'),
        get_string('configsitebotaddtogroup', 'message_max'),
        [''],
        $options
    ));

    $settings->add(new admin_setting_heading(
        'message_max_webhook',
        get_string('maxwebhook', 'message_max'),
        null,
    ));

    $url = new moodle_url('/message/output/max/maxconnect.php', ['sesskey' => sesskey(), 'action' => 'setwebhook']);
    $link = html_writer::tag(
        'a',
        get_config('message_max', 'webhook') ? get_string('unsetwebhook', 'message_max') : get_string('setwebhook', 'message_max'),
        ['href' => $url, 'class' => 'btn btn-danger']
    );

    $setting = new admin_setting_configcheckbox(
        'message_max/webhook',
        get_string('maxwebhook', 'message_max'),
        $link . '<br>' . get_string('configmaxwebhook', 'message_max'),
        false
    );
    $settings->add($setting);

    $settings->add(new admin_setting_configtext(
        'message_max/sitebotsecret',
        get_string('sitebotsecret', 'message_max'),
        get_string('configsitebotsecret', 'message_max'),
        null,
        PARAM_TEXT
    ));

    $context = context_user::instance($USER->id);
    $roles = get_default_enrol_roles($context);
    $settings->add(new admin_setting_configmultiselect(
        'message_max/sitebotmsgroles',
        get_string('roles'),
        get_string('configsitebotmsgroles', 'message_max'),
        [1, 3, 4],
        $roles
    ));

    $options = [
    0 => get_string('no'),
    1 => get_string('yes'),
    ];
    $settings->add(new admin_setting_configselect(
        'message_max/sitebotenablereports',
        get_string('reportenabler', 'message_max'),
        get_string('reportenabler_desc1', 'message_max') . ' ' . get_string('reportenabler_desc2', 'message_max'),
        0,
        $options
    ));

    $settings->add(new admin_setting_configcheckbox(
        'message_max/sitebotwarnreport',
        get_string('warning'),
        get_string('warnreport_desc', 'message_max'),
        true,
        true
    ));

    $options = [
        'email' => get_string('email'),
        'phone1' => get_string('phone1'),
        'phone2' => get_string('phone2'),
        'city' => get_string('city'),
        'country' => get_string('country'),
    ];
    $fields = profile_get_custom_fields();
    foreach ($fields as $f) {
        $options[$f->shortname] = format_string($f->name);
    }
    $settings->add(new admin_setting_configmultiselect(
        'message_max/sitebotreportfields',
        get_string('reportfields', 'message_max'),
        null,
        [],
        $options
    ));

    $options = [
    'phone1' => get_string('phone1'),
    'phone2' => get_string('phone2'),
    ];
    $fields = profile_get_custom_fields();
    foreach ($fields as $f) {
        $options['profile_field_' . $f->shortname] = format_string($f->name);
    }
    $settings->add(new admin_setting_configselect(
        'message_max/sitebotphonefield',
        get_string('phonefield', 'message_max'),
        get_string('phonefield_desc', 'message_max'),
        'phone2',
        $options
    ));

    $settings->add(new admin_setting_heading(
        'message_max_standart',
        get_string('configuration', 'core'),
        null,
    ));

    $options = ['' => get_string('parse_text', 'message_max'), 'HTML' => get_string('parse_html', 'message_max')];
    $settings->add(new admin_setting_configselect(
        'message_max/parsemode',
        get_string('parsemode', 'message_max'),
        get_string('configparsemode', 'message_max'),
        'HTML',
        $options
    ));

    $settings->add(new admin_setting_configcheckbox(
        'message_max/striptags',
        get_string('striptags', 'message_max'),
        get_string('configstriptags', 'message_max'),
        true
    ));

    $settings->add(new admin_setting_configcheckbox(
        'message_max/fullmessagehtml',
        get_string('fullmessagehtml', 'message_max'),
        get_string('configfullmessagehtml', 'message_max'),
        false
    ));

    $settings->add(new admin_setting_configcheckbox(
        'message_max/maxlog',
        get_string('maxlog', 'message_max'),
        get_string('configmaxlog', 'message_max', $CFG->tempdir),
        false
    ));

    $settings->add(new admin_setting_configcheckbox(
        'message_max/maxlogdump',
        get_string('maxlogdump', 'message_max'),
        get_string('configmaxlogdump', 'message_max'),
        false
    ));

    $settings->add(new admin_setting_configcheckbox(
        'message_max/maxwebhookdump',
        get_string('maxwebhookdump', 'message_max'),
        get_string('configmaxlogdump', 'message_max'),
        false
    ));

    $settings->add(new admin_setting_configexecutable(
        'message_max/tgext',
        get_string('tgext', 'message_max'),
        get_string('configtgext', 'message_max'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'message_max_donate',
        ' ',
        null,
    ));

    $plugininfo = \core_plugin_manager::instance()->get_plugin_info('message_max');
    $donate = get_string('donate', 'message_max', $plugininfo);

    $settings->add(new admin_setting_heading(
        'message_max',
        '',
        $donate,
    ));
}
