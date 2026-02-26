## MAX message output plugin for Moodle.

[![](https://img.shields.io/github/v/release/Snickser/moodle-message_output_max.svg)](https://github.com/Snickser/moodle-message_output_max/releases)
[![Build Status](https://github.com/Snickser/moodle-message_output_max/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/Snickser/moodle-message_output_max/actions/workflows/moodle-ci.yml)

This plugin provides Moodle messaging provider for MAX.

- Delayed sending function (queue) when the rate-limit is reached
- Automatic disabling of the user's subscription when the user blocks the bot.
- If there is an additional custom profile field "max_username", it will be filled in automatically.
- Invite to a news channel or group.
- Webhook mode (experimental!!)
- BotMode functional (info, courses list, events, etc.).
- Mistral.ai integration
