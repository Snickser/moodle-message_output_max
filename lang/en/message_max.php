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
 * Strings for MAX message plugin.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ainotconfigured'] = '❌ AI assistant is not configured';
$string['aiprovider'] = 'AI provider';
$string['aiprovider_desc'] = 'Select which AI provider to use for chatbot functionality (/ask command)';
$string['aiprovider_mistral'] = 'Mistral AI';
$string['aiprovider_openrouter'] = 'OpenRouter';
$string['alreadyconnected'] = '✅ Your MAX account is linked';
$string['askcleared'] = '🗑️ AI conversation history cleared';
$string['asknoquestion'] = '❓ Enter your question after /ask command

Example:
/ask Hello, how are you?';
$string['botanswer1'] = '🤔 I would reply privately, but we havent met yet ☺️';
$string['botanswer2'] = '👍 I replied privately';
$string['botask'] = '/ask - ask a question to AI assistant';
$string['botcertdownload'] = '📥 Download';
$string['botcertificates'] = '/certificates - issued certificates';
$string['botcerts'] = '📜 Your certificates:

';
$string['botcertselect'] = '📥 Select a certificate';
$string['botcertyour'] = '💾 Your certificate';
$string['botclear'] = '/clear - clear AI conversation history';
$string['botenrols'] = '🎓 Participation in courses:';
$string['botentertext'] = '✏️ Enter your message text';
$string['botevents'] = '🗓 Upcoming Events:

';
$string['botfaq'] = '⁉️ Frequently Asked Questions:';
$string['botfaqtext'] = '';
$string['bothelp'] = '👓 Helps
/info - platform information
/faq  - frequently asked questions
/lang - language switching
/courses - course list
/events - upcoming
/enrols - participation in courses
/progress - status of course elements';
$string['bothelp_anonymous'] = '👓 Helps
/info - platform information
/faq  - frequently asked questions';
$string['botidontknow'] = 'I dont know what this is 🤷🏻 /help';
$string['botlang'] = '🈯 Select language ({$a})';
$string['botmessagehelp'] = '/message - send group message';
$string['botmsgall'] = 'To all students';
$string['botpay'] = '🏦 Select amount {$a}';
$string['botpaydesc'] = 'To support the learning platform';
$string['botpaytitle'] = 'Donation 🕉';
$string['botstudents'] = '/students - personal data report';
$string['botuserid'] = '👑 User ID: {$a}';
$string['botuseridhelp'] = '/userid - select user';
$string['configfullmessagehtml'] = 'Get message from "$eventdata->fullmessagehtml" (if available), or from "fullmessage" if not set.';
$string['configmaxlog'] = 'Write debug info into {$a}/max.log file.';
$string['configmaxlogdump'] = 'For debugging purposes, write the message to a log file.';
$string['configmaxwebhook'] = 'This MAX webhook is for testing purposes, do not enable it, otherwise push button twice!';
$string['configparsemode'] = 'Formatting options: Text or HTML.';
$string['configsitebotaddtogroup'] = 'Automatically add a new user to the group/channel with the specified ID. First, add this chatbot to the desired group/channel with administrator rights.';
$string['configsitebotmsgroles'] = 'Who is allowed to send mass messages to course groups and use reports.';
$string['configsitebotname'] = 'This will be filled in automatically when you save the bot token.';
$string['configsitebotpay'] = 'Bot payment token for accepting payments.';
$string['configsitebotpaycosts'] = 'Separated by commas.';
$string['configsitebotsecret'] = 'Generated randomly and automatically if empty.';
$string['configsitebottoken'] = 'Enter the site bot token from Botfather here.';
$string['configsitebotusername'] = 'This will be filled in automatically when you save the bot token.';
$string['configstriptags'] = 'Strip all html tags from "Text" formatted message (in "HTML" mode unresolved tags are always removed).';
$string['configtgext'] = 'You may need to use an external messaging service, such as bypass ratelimit, or ensure that messages are delivered.';
$string['connectinstructions'] = 'Once you have clicked the link below, you will need to allow the link to open in MAX with
your MAX account. In MAX, click the "Start" button in the "{$a}" chat that opens to connect your account to Moodle.
Once completed, come back to this page and click "Save changes". Full documentation
<a href="https://docs.moodle.org/33/en/MAX_message_processor#Configuring_user_preferences" target="_blank">here</a>.';
$string['connectme'] = '<br><p style="color: blue;"><font size=+1><b>👉 Connect my account to MAX 👈</b></font></p>';
$string['connectmemenu'] = 'Connect my account to MAX';
$string['customfield'] = 'If there is an additional user profile field "max_username", it will be filled in automatically.';
$string['default'] = 'Default';
$string['donate'] = '<div>Plugin version: {$a->release} ({$a->versiondisk})</br>
You can find new versions of the plugin at <a href=https://github.com/Snickser/moodle-message_output_max>GitHub.com</a>
<img src="https://img.shields.io/github/v/release/Snickser/moodle-message_output_max.svg"><br>
Please send me some <a href="https://yoomoney.ru/fundraise/143H2JO3LLE.240720">donate</a>😊</div>
TON UQA1vhoJmBLgzTHKbJuuscr6UPwnP9TEH3eJYFKIiVgUIaro<br>
BTC 1GFTTPCgRTC8yYL1gU7wBZRfhRNRBdLZsq<br>
TRX TRGMc3b63Lus6ehLasbbHxsb2rHky5LbPe<br>
<iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=143H2JO3LLE.240720"
width="330" height="50" frameborder="0" allowtransparency="true" scrolling="no"></iframe>';
$string['enter'] = 'Enter';
$string['enter_phone'] = 'For better interaction with curators, please provide your mobile phone number.';
$string['enter_time'] = 'The date and time are specified in mnemonic or one of the standard formats, for example: YY-MM-DD HH:MM';
$string['firstregister'] = 'First, register on the website and enable notifications via MAX. {$a}';
$string['fullmessagehtml'] = 'Use fullmessagehtml';
$string['groupinvite'] = '⚠️ Please join our news channel to stay up to date with news and events 👉 <a href="{$a->link}">{$a->title}</a>';
$string['groupinvitedone'] = 'You have been added to our news channel <a href="{$a->link}">{$a->title}</a>';
$string['maxbottoken'] = 'MAX bot token';
$string['maxchatid'] = 'MAX chat id';
$string['maxlog'] = 'Enable logging';
$string['maxlogdump'] = 'Dump message to log';
$string['maxwebhook'] = 'Webhook';
$string['maxwebhookdump'] = 'Dump webhook data to log';
$string['mistralapikey'] = 'Mistral API key';
$string['mistralapikey_desc'] = 'Get your API key at https://console.mistral.ai/';
$string['mistralconnectionerror'] = 'Error connecting to Mistral AI';
$string['mistralconnectionok'] = 'Successfully connected to Mistral AI';
$string['mistralerror'] = 'Error getting response from AI';
$string['mistralmodel'] = 'Mistral model';
$string['mistralmodel_desc'] = 'e.g., mistral-small-latest, mistral-medium-latest, mistral-large-latest';
$string['mistralnotconfigured'] = 'AI assistant is not configured';
$string['mistralprompt'] = 'System prompt';
$string['mistralprompt_default'] = 'You are a helpful assistant for the Moodle educational platform. Answer user questions briefly and to the point.';
$string['mistralprompt_desc'] = 'Instructions for the AI assistant';
$string['mistralsettings'] = 'Mistral AI (chatbot)';
$string['mistralsettings_desc'] = 'Mistral AI integration settings for answering user questions via /ask command';
$string['mistraltranscriptionmodel'] = 'Mistral transcription model';
$string['mistraltranscriptionmodel_desc'] = 'e.g., voxtral-mini-latest';
$string['notconfigured'] = 'The MAX server hasn\'t been configured so MAX messages cannot be sent';
$string['openrouterapikey'] = 'OpenRouter API key';
$string['openrouterapikey_desc'] = 'Get your API key at https://openrouter.ai/keys';
$string['openrouterconnectionerror'] = 'Error connecting to OpenRouter';
$string['openrouterconnectionok'] = 'Successfully connected to OpenRouter';
$string['openroutererror'] = 'Error getting response from AI';
$string['openroutermaxtokens'] = 'Max tokens';
$string['openroutermaxtokens_desc'] = 'Maximum number of tokens in the response';
$string['openroutermodel'] = 'OpenRouter model';
$string['openroutermodel_desc'] = 'e.g., meta-llama/llama-3-8b-instruct:free (free), google/gemma-2-9b-it:free (free), openai/gpt-4o-mini (paid)';
$string['openrouternotconfigured'] = 'AI assistant is not configured';
$string['openrouterprompt'] = 'System prompt';
$string['openrouterprompt_default'] = 'You are a helpful assistant for the Moodle educational platform. Answer user questions briefly and to the point.';
$string['openrouterprompt_desc'] = 'Instructions for the AI assistant';
$string['openroutersettings'] = 'OpenRouter (chatbot)';
$string['openroutersettings_desc'] = 'OpenRouter integration settings for answering user questions via /ask command. OpenRouter provides access to multiple AI models from different providers.';
$string['openroutertemperature'] = 'Temperature';
$string['openroutertemperature_desc'] = 'Controls randomness: lower values make output more focused, higher values more creative (0.0-2.0)';
$string['parse_html'] = 'HTML format';
$string['parse_text'] = 'Text only';
$string['parsemode'] = 'Parse mode';
$string['phonefield'] = 'Phone number save field';
$string['phonefield_desc'] = 'This field will be filled automatically after the student submits their contact information.';
$string['pluginname'] = 'MAX';
$string['provide'] = '📱 Provide a phone number';
$string['provide_help'] = 'Provide a phone number';
$string['removemax'] = 'Remove MAX connection';
$string['reportenabler'] = 'Enable users personal data report';
$string['reportenabler_desc1'] = '<font color=red>Please note that users personal data is transferred to third-party MAX servers, this may violate the law of your country.</font>';
$string['reportenabler_desc2'] = 'This option enables selected roles to view personal data of course students.';
$string['reportfields'] = 'Fields in report';
$string['requirehttps'] = 'Site must use HTTPS for MAX\'s webhook function.';
$string['setupinstructions'] = 'Create a new MAX Bot using MasterBot. Click the Botfather link below and open it in MAX.
Use the "/newbot" command in MAX to start creating the bot. You will need to specify a botname, for example "{$a->name}", and a
unique bot username, for example "{$a->username}".';
$string['setwebhook'] = 'Setup MAX webhook';
$string['setwebhooksuccess'] = 'Webhook is set successfully';
$string['sitebotaddtogroup'] = 'Invite new user to news channel or group';
$string['sitebotname'] = 'Bot name for site';
$string['sitebotpay'] = 'Payment token';
$string['sitebotpaycosts'] = 'Predefined amounts';
$string['sitebotsecret'] = 'Webhook secret';
$string['sitebottoken'] = 'Bot token for site';
$string['sitebottokennotsetup'] = 'Bot token for site must be specified in plugin settings.';
$string['sitebotusername'] = 'Bot username for site';
$string['striptags'] = 'Strip tags';
$string['tgext'] = 'Path to external sender';
$string['unsetwebhook'] = 'Unset MAX webhook';
$string['unsetwebhooksuccess'] = 'Webhook removed';
$string['usehelp'] = 'Use /help';
$string['usernamefield'] = 'Username save field';
$string['usernamefield_desc'] = 'Default short name for extended user profile field is "max_username".';
$string['wait'] = '🕑 Please wait, the file is loading...';
$string['waitai'] = '⏳ Preparing an answer...';
$string['warning'] = 'Warning';
$string['warnreport_desc'] = 'Display warning before print report.';
$string['welcome'] = '✅ Your account has been successfully linked!';
