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
 * OpenRouter API client for MAX message plugin.
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
 * Class openrouter_ai
 *
 * Provides integration with OpenRouter API for chatbot functionality.
 * OpenRouter provides unified access to multiple AI models from different providers.
 *
 * @package     message_max
 * @copyright   2026 Alex Orlov <snickser@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openrouter_ai {
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
        global $CFG;

        $this->config = get_config('message_max');
        $this->apikey = !empty($this->config->openrouterapikey) ? $this->config->openrouterapikey : '';
        // Use a free/accessible model as default - change to your preferred model in settings.
        $this->model = !empty($this->config->openroutermodel) ? $this->config->openroutermodel : 'meta-llama/llama-3-8b-instruct:free';
        $this->temperature = isset($this->config->openroutertemperature) && $this->config->openroutertemperature !== ''
            ? (float)$this->config->openroutertemperature : 0.3;
        $this->maxtokens = isset($this->config->openroutermaxtokens) && $this->config->openroutermaxtokens !== ''
            ? (int)$this->config->openroutermaxtokens : 2048;

        // Get system prompt from config or use default.
        if (!empty($this->config->openrouterprompt)) {
            $this->systemprompt = $this->config->openrouterprompt;
        } else {
            $this->systemprompt = get_string('openrouterprompt_default', 'message_max');
        }

        $this->client = new http_client([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apikey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'Moodle-OpenRouter-Integration',
            ],
            'timeout' => 120,
        ]);
    }

    /**
     * Check if OpenRouter is configured and enabled.
     *
     * @return bool True if configured and enabled.
     */
    public function is_enabled(): bool {
        return !empty($this->apikey);
    }

    /**
     * Send a chat completion request to OpenRouter.
     *
     * @param string $message User message.
     * @param int $userid Moodle user ID for context.
     * @return string AI response text.
     */
    public function chat(string $message, int $userid = null): string {
        global $DB, $USER;

        if (!$this->is_enabled()) {
            return get_string('openrouternotconfigured', 'message_max');
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

        // Build messages array for OpenRouter API.
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

            // Log request for debugging.
            debugging('OpenRouter request: model=' . $this->model . ', messages count=' . count($messages), DEBUG_DEVELOPER);

            // OpenRouter uses the same endpoint as OpenAI but we need to ensure proper headers.
            $response = $this->client->post('https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'HTTP-Referer' => $CFG->wwwroot ?? '',
                    'X-Title' => 'Moodle LMS',
                ],
                'json' => $requestdata,
            ]);

            $statuscode = $response->getStatusCode();
            $bodycontent = $response->getBody()->getContents();

            // Log raw response for debugging.
            debugging('OpenRouter raw response (HTTP ' . $statuscode . '): ' . substr($bodycontent, 0, 500), DEBUG_DEVELOPER);

            $body = json_decode($bodycontent, true);

            // Log error if status code is not 200.
            if ($statuscode !== 200) {
                debugging('OpenRouter API error (HTTP ' . $statuscode . '): ' . $bodycontent, DEBUG_DEVELOPER);
                if (isset($body['error']['message'])) {
                    return get_string('openroutererror', 'message_max') . ': ' . $body['error']['message'];
                }
                return get_string('openroutererror', 'message_max');
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging('OpenRouter JSON decode error: ' . json_last_error_msg() . ' - Raw response: ' . substr($bodycontent, 0, 200), DEBUG_DEVELOPER);
                return get_string('openroutererror', 'message_max');
            }

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

            // Log error if response structure is unexpected.
            debugging('OpenRouter unexpected response structure: ' . json_encode($body), DEBUG_DEVELOPER);
            return get_string('openroutererror', 'message_max');
        } catch (\Exception $e) {
            debugging('OpenRouter exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return get_string('openroutererror', 'message_max') . ': ' . $e->getMessage();
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
            'message_max_openrouter',
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

        return $DB->insert_record('message_max_openrouter', $record);
    }

    /**
     * Clear conversation history for a user.
     *
     * @param int $userid Moodle user ID.
     * @return bool True on success.
     */
    public function clear_history(int $userid): bool {
        global $DB;

        return $DB->delete_records('message_max_openrouter', ['userid' => $userid]);
    }

    /**
     * Test the OpenRouter connection.
     *
     * @return array Result array with success status and message.
     */
    public function test_connection(): array {
        try {
            $response = $this->client->get('/models');
            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['data'])) {
                return [
                    'success' => true,
                    'message' => get_string('openrouterconnectionok', 'message_max'),
                    'models' => array_column($body['data'], 'id'),
                ];
            }

            return [
                'success' => false,
                'message' => get_string('openrouterconnectionerror', 'message_max'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('openrouterconnectionerror', 'message_max') . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get available models from OpenRouter API.
     *
     * @return array List of model data.
     */
    public function get_available_models(): array {
        try {
            // OpenRouter supports both authenticated and unauthenticated model listing.
            // Use the public endpoint to get all available models.
            $response = $this->client->get('https://openrouter.ai/api/v1/models', [
                'headers' => [
                    'HTTP-Referer' => $CFG->wwwroot ?? '',
                    'X-Title' => 'Moodle LMS',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                debugging('OpenRouter API returned status code: ' . $response->getStatusCode(), DEBUG_DEVELOPER);
                return [];
            }

            $body = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging('Failed to decode OpenRouter models JSON: ' . json_last_error_msg(), DEBUG_DEVELOPER);
                return [];
            }

            if (isset($body['data'])) {
                return $body;
            }

            debugging('OpenRouter response does not contain "data" key', DEBUG_DEVELOPER);
        } catch (\Exception $e) {
            debugging('Failed to get OpenRouter models: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return [];
    }

    /**
     * Transcribe an audio file using OpenRouter (if supported by selected model).
     *
     * @param string $filepath Path to the audio file.
     * @param string|null $language Language code (optional, auto-detect if not specified).
     * @return string Transcribed text or error message.
     */
    public function transcribe_audio_file(string $filepath, ?string $language = null): string {
        if (!$this->is_enabled()) {
            return get_string('openrouternotconfigured', 'message_max');
        }

        // Check if file exists.
        if (!file_exists($filepath)) {
            return get_string('filenotfound', 'error');
        }

        // OpenRouter doesn't have a dedicated transcription endpoint like Mistral.
        // This is a placeholder for future implementation if/when supported.
        debugging('OpenRouter audio transcription is not currently supported', DEBUG_DEVELOPER);
        return get_string('openroutertranscriptionnotsupported', 'message_max');
    }
}
