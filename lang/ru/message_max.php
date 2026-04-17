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
 * Strings for component 'message_max', language 'ru', version '4.3'.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ainotconfigured'] = '❌ AI-ассистент не настроен';
$string['aiprovider'] = 'AI провайдер';
$string['aiprovider_desc'] = 'Выберите, какой AI провайдер использовать для функционала чат-бота (команда /ask)';
$string['aiprovider_mistral'] = 'Mistral AI';
$string['aiprovider_openrouter'] = 'OpenRouter';
$string['alreadyconnected'] = '✅ Ваш MAX аккаунт подключен';
$string['askcleared'] = '🗑️ История переписки с ИИ очищена';
$string['asknoquestion'] = '❓ Введите ваш вопрос после команды /ask

Пример:
/ask Привет, как дела?';
$string['botanswer1'] = '🤔 Ответил бы в привате, но мы пока не знакомы ☺️';
$string['botanswer2'] = '👍 Ответил в приват';
$string['botask'] = '/ask - задать вопрос ИИ-ассистенту';
$string['botcertdownload'] = '📥 Скачать';
$string['botcertificates'] = '/certificates - выданные сертификаты';
$string['botcerts'] = '📜 <b>Ваши сертификаты</b>

';
$string['botcertselect'] = '📥 Выберите сертификат';
$string['botcertyour'] = '💾 Ваш сертификат';
$string['botclear'] = '/clear - очистить историю переписки с ИИ';
$string['botenrols'] = '🎓 <b>Участие в курсах</b>';
$string['botentertext'] = '✏️ Введите текст сообщения';
$string['botevents'] = '🗓 <b>Предстоящие события</b>

';
$string['botfaq'] = '⁉️ Часто задаваемые вопросы:';
$string['botfaqtext'] = '';
$string['bothelp'] = '👓 Подсказки
/info - информация о платформе
/lang - переключение языка
/faq - часто задаваемые вопросы
/courses - список всех курсов
/enrols - участие в курсах
/events - предстоящие события
/progress - статус элементов курса';
$string['bothelp_anonymous'] = '👓 Подсказки
/info - информация о платформе
/faq  - часто задаваемые вопросы';
$string['botidontknow'] = 'Не знаю что это такое 🤷🏻 /help';
$string['botlang'] = '🈯 Выбрать язык ({$a})';
$string['botmessagehelp'] = '/message - отправить групповое сообщение';
$string['botmsgall'] = '🔺 Всем студентам курса';
$string['botpay'] = '🏦 Выберите сумму {$a}';
$string['botpaydesc'] = 'На поддержание учебной платформы';
$string['botpaytitle'] = '🕉 Пожертвование 🕉';
$string['botuserid'] = '👑 Пользователь 🆔 {$a}';
$string['botuseridhelp'] = '/userid - сменить пользователя';
$string['configfullmessagehtml'] = 'Получать сообщение из "$eventdata->fullmessagehtml" (если доступно), или из "fullmessage", если не установлено.';
$string['configmaxlog'] = 'Записывать отладочную информацию в файл {$a}/max.log.';
$string['configmaxlogdump'] = 'В отладочных целях записывать сообщение в файл лога.';
$string['configmaxwebhook'] = 'Этот MAX webhook предназначен для тестовых целей, не включайте его, иначе нажимайте кнопку дважды!';
$string['configparsemode'] = 'Опции форматирования: Текст или HTML.';
$string['configsitebotaddtogroup'] = 'Автоматически добавлять нового пользователя в группу/канал с указанным ID. Сначала добавьте этого чат-бота в желаемую группу/канал с правами администратора.';
$string['configsitebotmsgroles'] = 'Кому разрешено отправлять массовые сообщения в группы курсов и использовать отчёты.';
$string['configsitebotname'] = 'Будет заполнено автоматически, когда Вы сохраните токен бота.';
$string['configsitebotsecret'] = 'Генерируется случайно и автоматически, если пусто.';
$string['configsitebottoken'] = 'Введите сюда токен бота сайта, полученный от Botfather.';
$string['configsitebotusername'] = 'Будет заполнено автоматически, когда Вы сохраните токен бота.';
$string['configstriptags'] = 'Удалять все HTML-теги из сообщения, отформатированного как "Текст" (в режиме "HTML" неразрешённые теги всегда удаляются).';
$string['configtgext'] = 'Возможно, вам потребуется использовать внешний сервис обмена сообщениями, например, для обхода ограничения скорости или обеспечения доставки сообщений.';
$string['connectinstructions'] = 'После того, как вы нажмёте ссылку ниже, вам нужно будет разрешить открытие ссылки в MAX с вашей учётной записью MAX. В MAX нажмите кнопку «Start» в открывшемся чате «{$a}», чтобы подключить свою учётную запись.<br>После завершения вернитесь на эту страницу и нажмите «Save changes (Сохранить изменения)». Полную инструкцию <a href="https://docs.moodle.org/33/en/MAX_message_processor#Configuring_user_preferences" target="_blank">читаем здесь</a>.';
$string['connectme'] = '<br><p style="color: blue;"><font size=+1><b>👉 Подключить свой аккаунт к MAX 👈</b></font></p>';
$string['connectmemenu'] = '⚠️ Подключить свой аккаунт к MAX';
$string['customfield'] = 'Если существует дополнительное поле профиля пользователя "max_username", оно будет заполнено автоматически.';
$string['default'] = 'по умолчанию';
$string['donate'] = '<div>Версия плагина: {$a->release} ({$a->versiondisk})</br>
Вы можете найти новые версии плагина на <a href=https://github.com/Snickser/moodle-message_output_max>GitHub.com</a>
<img src="https://img.shields.io/github/v/release/Snickser/moodle-message_output_max.svg"><br>
Пожалуйста, отправьте мне немного <a href="https://yoomoney.ru/fundraise/143H2JO3LLE.240720">доната</a>😊</div>
<iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=143H2JO3LLE.240720"
width="330" height="35" frameborder="0" allowtransparency="true" scrolling="no"></iframe>
TON UQA1vhoJmBLgzTHKbJuuscr6UPwnP9TEH3eJYFKIiVgUIaro<br>
BTC 1GFTTPCgRTC8yYL1gU7wBZRfhRNRBdLZsq<br>
TRX TRGMc3b63Lus6ehLasbbHxsb2rHky5LbPe<br>
';
$string['enter'] = 'Введите';
$string['enter_phone'] = 'Для лучшего взаимодействия с кураторами, пожалуйста, укажите номер своего мобильного телефона в профиле на портале, или нажмите кнопку внизу.';
$string['enter_time'] = 'Дата и время указываются в мнемоническом или в одном из стандартных форматов, например: YY-MM-DD HH:MM';
$string['firstregister'] = 'Сначала зарегистрируйтесь на сайте, и подключите уведомления через MAX. {$a}';
$string['fullmessagehtml'] = 'Использовать fullmessagehtml';
$string['groupinvite'] = 'Пожалуйста присоединяйтесь к нашему новостному каналу, чтобы быть в курсе новостей и событий 👉 <a href="{$a->link}">{$a->title}
{$a->desc}</a>';
$string['groupinvitedone'] = 'Вы были добавлены в наш новостной канал <a href="{$a->link}">{$a->title}
{$a->desc}</a>';
$string['maxbottoken'] = 'Токен бота MAX';
$string['maxchatid'] = 'ID чата MAX';
$string['maxlog'] = 'Включить логирование';
$string['maxlogdump'] = 'Дамп сообщения в лог';
$string['maxwebhook'] = 'Webhook';
$string['maxwebhookdump'] = 'Дамп данных webhook в лог';
$string['mistralapikey'] = 'API ключ Mistral';
$string['mistralapikey_desc'] = 'Получите API ключ на https://console.mistral.ai/';
$string['mistralconnectionerror'] = 'Ошибка подключения к Mistral AI';
$string['mistralconnectionok'] = 'Подключение к Mistral AI успешно установлено';
$string['mistralerror'] = 'Ошибка при получении ответа от AI';
$string['mistralmodel'] = 'Модель Mistral';
$string['mistralmodel_desc'] = 'Например: mistral-small-latest, mistral-medium-latest, mistral-large-latest';
$string['mistralnotconfigured'] = '❌ ИИ-ассистент не настроен';
$string['mistralprompt'] = 'Системный промпт';
$string['mistralprompt_default'] = 'Вы — полезный ассистент образовательной платформы Moodle. Отвечайте на вопросы пользователей кратко и по делу.';
$string['mistralprompt_desc'] = 'Инструкция для AI-ассистента';
$string['mistralsettings'] = 'Mistral AI (чат-бот)';
$string['mistralsettings_desc'] = 'Настройки интеграции с Mistral AI для ответов на вопросы пользователей через команду /ask';
$string['mistraltranscriptionmodel'] = 'Модель транскрипции Mistral';
$string['mistraltranscriptionmodel_desc'] = 'Например: voxtral-mini-latest';
$string['notconfigured'] = 'Сервер MAX не настроен, поэтому сообщения MAX не могут быть отправлены';
$string['openrouterapikey'] = 'API ключ OpenRouter';
$string['openrouterapikey_desc'] = 'Получите API ключ на https://openrouter.ai/keys';
$string['openroutermaxtokens'] = 'Максимум токенов';
$string['openroutermaxtokens_desc'] = 'Максимальное количество токенов в ответе';
$string['openroutermodel'] = 'Модель OpenRouter';
$string['openroutermodel_desc'] = 'Например: meta-llama/llama-3-8b-instruct:free (бесплатно), google/gemma-2-9b-it:free (бесплатно), openai/gpt-4o-mini (платно)';
$string['openrouterprompt'] = 'Системный промпт';
$string['openrouterprompt_default'] = 'Вы — полезный ассистент образовательной платформы Moodle. Отвечайте на вопросы пользователей кратко и по делу.';
$string['openrouterprompt_desc'] = 'Инструкция для AI-ассистента';
$string['openroutersettings'] = 'OpenRouter (чат-бот)';
$string['openroutersettings_desc'] = 'Настройки интеграции с OpenRouter для ответов на вопросы пользователей через команду /ask. OpenRouter предоставляет доступ к нескольким AI моделям от разных провайдеров.';
$string['openroutertemperature'] = 'Температура';
$string['openroutertemperature_desc'] = 'Контролирует случайность: меньшие значения делают вывод более сфокусированным, большие — более креативным (0.0-2.0)';
$string['parse_html'] = 'HTML формат';
$string['parse_text'] = 'Только текст';
$string['parsemode'] = 'Режим парсинга';
$string['phonefield'] = 'Поле для сохранения номера телефона';
$string['phonefield_desc'] = 'Это поле будет заполнено автоматически после того, как студент предоставит свою контактную информацию.';
$string['pluginname'] = 'MAX';
$string['provide'] = '☎️ Отправить номер телефона';
$string['provide_help'] = 'Нажмите кнопку';
$string['removemax'] = 'Отключиться от MAX';
$string['reportenabler'] = 'Включить отчёт о персональных данных пользователей';
$string['reportenabler_desc1'] = '<font color=red>Обратите внимание, что персональные данные пользователей передаются на сторонние серверы MAX, это может нарушать законы вашей страны.</font>';
$string['reportenabler_desc2'] = 'Эта опция позволяет выбранным ролям просматривать персональные данные студентов курса.';
$string['reportfields'] = 'Поля в отчёте';
$string['setupinstructions'] = 'Создайте новый бот MAX, используя MasterBot. Перейдите по ссылке Botfather ниже и откройте MAX.
Используйте команду "/newbot" в MAX для начала создания бота. Вам надо будет задать название бота, например "{$a->name}" и уникальное имя бота, например "{$a->username}".';
$string['setwebhook'] = 'Установить MAX webhook';
$string['sitebotaddtogroup'] = 'Пригласить нового пользователя в новостной канал или группу';
$string['sitebotname'] = 'Название бота для сайта';
$string['sitebotsecret'] = 'Секрет webhook';
$string['sitebottoken'] = 'Токен бота для сайта';
$string['sitebottokennotsetup'] = 'Токен бота для сайта должен быть указан в настройках плагина.';
$string['sitebotusername'] = 'Ник бота для сайта';
$string['striptags'] = 'Удалить теги';
$string['tgext'] = 'Путь к внешнему отправителю';
$string['unsetwebhook'] = 'Отключить MAX webhook';
$string['usernamefield'] = 'Поле для сохранения имени пользователя';
$string['usernamefield_desc'] = 'По умолчанию короткое имя для расширенного поля профиля пользователя — "max_username".';
$string['wait'] = '🕑 Пожалуйста подождите, файл загружается...';
$string['waitai'] = '⏳ Готовлю ответ...';
$string['warning'] = 'Предупреждение';
$string['warnreport_desc'] = 'Выводить предупреждение перед выдачей отчёта.';
$string['welcome'] = '✅ Ваш аккаунт успешно подключен!';
