<?php
/**
 * Nextcloud Talk Bot - Admin Settings Page
 */
declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Settings;

use OCA\NextcloudTalkBot\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use OCP\Settings\ISettings;
use OCP\IConfig;

class Admin implements ISettings {

    public function __construct(
        private IConfig $config,
        private IInitialStateService $initialStateService,
        private SettingsService $settingsService
    ) {}

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        // Load current settings into initial state
        $settings = $this->settingsService->getAdminSettings();
        $this->initialStateService->provideInitialState('talk_bot_settings', $settings);

        return new TemplateResponse('talk_bot', 'admin', [], '');
    }

    /**
     * @return string
     */
    public function getSection(): string {
        return 'talk_bot';
    }

    /**
     * @return int
     */
    public function getPriority(): int {
        return 100;
    }
}
