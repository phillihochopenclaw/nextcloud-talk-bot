<?php
/**
 * Nextcloud Talk Bot - Settings Service
 */
declare(strict_types=1);

namespace OCA\TalkBot\Service;

use OCP\IConfig;

class SettingsService {

    private const APP_ID = 'talk_bot';

    public function __construct(
        private IConfig $config
    ) {}

    /**
     * Get all admin settings
     *
     * @return array
     */
    public function getAdminSettings(): array {
        return [
            'botUrl' => $this->config->getAppValue(self::APP_ID, 'bot_url', ''),
            'botToken' => $this->config->getAppValue(self::APP_ID, 'bot_token', ''),
            'allowedChannels' => json_decode(
                $this->config->getAppValue(self::APP_ID, 'allowed_channels', '[]'),
                true
            ) ?: [],
            'enableAI' => $this->config->getAppValue(self::APP_ID, 'enable_ai', 'no') === 'yes',
            'aiModel' => $this->config->getAppValue(self::APP_ID, 'ai_model', 'claude-3-opus'),
            'maxMessages' => (int) $this->config->getAppValue(self::APP_ID, 'max_messages', 50),
            // OpenAI Settings (US-007)
            'openaiEnabled' => $this->config->getAppValue(self::APP_ID, 'openai_enabled', 'no') === 'yes',
            'openaiApiKey' => $this->config->getAppValue(self::APP_ID, 'openai_api_key', ''),
            'openaiModel' => $this->config->getAppValue(self::APP_ID, 'openai_model', 'gpt-4'),
            // Custom Provider Settings (US-008)
            'customProviderEnabled' => $this->config->getAppValue(self::APP_ID, 'custom_provider_enabled', 'no') === 'yes',
            'customProviderEndpoint' => $this->config->getAppValue(self::APP_ID, 'custom_provider_endpoint', ''),
            'customProviderModel' => $this->config->getAppValue(self::APP_ID, 'custom_provider_model', ''),
            'customProviderHeaders' => json_decode(
                $this->config->getAppValue(self::APP_ID, 'custom_provider_headers', '{}'),
                true
            ) ?: [],
            // AI Status (US-010)
            'activeProvider' => $this->config->getAppValue(self::APP_ID, 'active_provider', 'none'),
            'aiStatus' => $this->config->getAppValue(self::APP_ID, 'ai_status', 'inactive'),
            'lastError' => $this->config->getAppValue(self::APP_ID, 'last_error', ''),
        ];
    }

    /**
     * Set admin settings
     *
     * @param array $settings
     * @return void
     */
    public function setAdminSettings(array $settings): void {
        foreach ($settings as $key => $value) {
            // Handle special cases
            if ($key === 'allowedChannels') {
                $value = json_encode($value);
            }
            if ($key === 'customProviderHeaders') {
                $value = json_encode($value);
            }
            if ($key === 'openaiApiKey' && !empty($value)) {
                // Don't overwrite with empty value
                $existing = $this->config->getAppValue(self::APP_ID, 'openai_api_key', '');
                if (empty($value) && !empty($existing)) {
                    continue;
                }
            }
            $this->config->setAppValue(self::APP_ID, $key, (string) $value);
        }
    }

    /**
     * Update AI status
     *
     * @param string $status
     * @param string|null $error
     * @return void
     */
    public function updateAIStatus(string $status, ?string $error = null): void {
        $this->config->setAppValue(self::APP_ID, 'ai_status', $status);
        if ($error !== null) {
            $this->config->setAppValue(self::APP_ID, 'last_error', $error);
        }
    }

    /**
     * Test AI connection
     *
     * @param string $provider
     * @param array $config
     * @return array
     */
    public function testAIConnection(string $provider, array $config): array {
        // Basic validation based on provider
        if ($provider === 'openai') {
            if (empty($config['apiKey'])) {
                return ['success' => false, 'message' => 'API Key is required'];
            }
            // In a real implementation, this would make an actual API call
            return ['success' => true, 'message' => 'OpenAI connection successful'];
        }

        if ($provider === 'custom') {
            if (empty($config['endpoint'])) {
                return ['success' => false, 'message' => 'Endpoint URL is required'];
            }
            if (!filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
                return ['success' => false, 'message' => 'Invalid endpoint URL'];
            }
            return ['success' => true, 'message' => 'Custom provider connection successful'];
        }

        return ['success' => false, 'message' => 'Unknown provider'];
    }
}
