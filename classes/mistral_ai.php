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
 * Mistral AI API client for MAX message plugin.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace message_max;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/classes/http_client.php');

use core\http_client;

/**
 * Class mistral_ai
 *
 * Provides integration with Mistral AI API for chatbot functionality.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mistral_ai {
    /** @var array Configuration array */
    private $config;

    /** @var string API key */
    private $apikey;

    /** @var string Model to use */
    private $model;

    /** @var float Temperature setting */
    private $temperature;

    /** @var int Max tokens */
    private $maxtokens;

    /** @var string System prompt */
    private $systemprompt;

    /** @var http_client HTTP client instance */
    private $client;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = get_config('message_max');
        $this->apikey = !empty($this->config->mistralapikey) ? $this->config->mistralapikey : '';
        $this->model = !empty($this->config->mistralmodel) ? $this->config->mistralmodel : 'mistral-medium-latest';
        $this->temperature = isset($this->config->mistraltemperature) && $this->config->mistraltemperature !== ''
        ? (float)$this->config->mistraltemperature : 0.3;
        $this->maxtokens = isset($this->config->mistralmaxtokens) && $this->config->mistralmaxtokens !== ''
        ? (int)$this->config->mistralmaxtokens : 2048;

        // Get system prompt from config or use default.
        if (!empty($this->config->mistralprompt)) {
            $this->systemprompt = $this->config->mistralprompt;
        } else {
            $this->systemprompt = get_string('mistralprompt_default', 'message_max');
        }

        $this->client = new http_client([
            'base_uri' => 'https://api.mistral.ai',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apikey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'Moodle-Mistral-Integration',
            ],
            'timeout' => 60,
        ]);
    }

    /**
     * Check if Mistral AI is configured and enabled.
     *
     * @return bool True if configured and enabled.
     */
    public function is_enabled(): bool {
        return !empty($this->apikey);
    }

    /**
     * Send a chat completion request to Mistral AI.
     *
     * @param string $message User message.
     * @param int $userid Moodle user ID for context.
     * @return string AI response text.
     */
    public function chat(string $message, int $userid = null): string {
        global $DB, $USER;

        if (!$this->is_enabled()) {
            return get_string('mistralnotconfigured', 'message_max');
        }

        // Get user context if available.
        $usercontext = null;
        if ($userid) {
            $user = $DB->get_record('user', ['id' => $userid]);
            if ($user) {
                $usercontext = [
                    'login' => $user->username,
                    'fullname' => fullname($user),
                    'email' => $user->email,
                ];
            }
        }

        // Build messages array for Mistral API.
        $messages = [];

        // Add system prompt.
        $systemprompt = $this->systemprompt;
        if ($usercontext) {
            $systemprompt .= "\n\nUser context:\n" .
                           "Name: {$usercontext['fullname']}\n";
        }
        $messages[] = [
            'role' => 'system',
            'content' => $systemprompt,
        ];

        // Get conversation history (last 20 messages).
        $history = $this->get_conversation_history($userid, 20);
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // Add current user message.
        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        try {
            $requestdata = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxtokens,
                'top_p' => 1,
                'stream' => false,
            ];

            $response = $this->client->post('/v1/chat/completions', [
                'json' => $requestdata,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['choices'][0]['message']['content'])) {
                // Thinking model's use array.
                if (is_array($body['choices'][0]['message']['content'])) {
                    $answer = trim($body['choices'][0]['message']['content'][1]['text']);
                } else {
                    $answer = trim($body['choices'][0]['message']['content']);
                }
                // Save user message and AI response to history.
                if ($userid) {
                    $this->save_message($userid, $message, true);
                    $this->save_message($userid, $answer, false);
                }

                return $answer;
            }

            // Log error if response is unexpected.
            return get_string('mistralerror', 'message_max');
        } catch (\Exception $e) {
            return get_string('mistralerror', 'message_max') . ': ' . $e->getMessage();
        }
    }

    /**
     * Get conversation history from database.
     *
     * @param int $userid Moodle user ID.
     * @param int $limit Maximum number of messages to retrieve.
     * @return array Array of conversation messages.
     */
    public function get_conversation_history(int $userid, int $limit = 20): array {
        global $DB;

        if (!$userid) {
            return [];
        }

        $records = $DB->get_records(
            'message_max_mistral',
            ['userid' => $userid],
            'timecreated DESC',
            '*',
            0,
            $limit
        );

        // Reverse to get chronological order.
        $records = array_reverse($records);

        $messages = [];
        foreach ($records as $record) {
            $messages[] = [
                'role' => $record->isuser ? 'user' : 'assistant',
                'content' => $record->message,
                'timecreated' => $record->timecreated,
            ];
        }

        return $messages;
    }

    /**
     * Save a message to conversation history.
     *
     * @param int $userid Moodle user ID.
     * @param string $message Message content.
     * @param bool $isuser True if message is from user.
     * @return int Record ID.
     */
    public function save_message(int $userid, string $message, bool $isuser): int {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->message = $message;
        $record->isuser = $isuser ? 1 : 0;
        $record->timecreated = time();

        return $DB->insert_record('message_max_mistral', $record);
    }

    /**
     * Clear conversation history for a user.
     *
     * @param int $userid Moodle user ID.
     * @return bool True on success.
     */
    public function clear_history(int $userid): bool {
        global $DB;

        return $DB->delete_records('message_max_mistral', ['userid' => $userid]);
    }

    /**
     * Test the Mistral AI connection.
     *
     * @return array Result array with success status and message.
     */
    public function test_connection(): array {
        try {
            $response = $this->client->get('/v1/models');
            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['data'])) {
                return [
                    'success' => true,
                    'message' => get_string('mistralconnectionok', 'message_max'),
                    'models' => array_column($body['data'], 'id'),
                ];
            }

            return [
                'success' => false,
                'message' => get_string('mistralconnectionerror', 'message_max'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('mistralconnectionerror', 'message_max') . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get available models from Mistral API.
     *
     * @return array List of model IDs.
     */
    public function get_available_models(): array {
        try {
            $response = $this->client->get('/v1/models');
            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['data'])) {
                return $body;
            }
        } catch (\Exception $e) {
            debugging('Failed to get Mistral models: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
