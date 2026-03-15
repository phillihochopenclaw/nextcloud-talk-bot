<?php
/**
 * Nextcloud Talk Bot - Application Bootstrap
 */
declare(strict_types=1);

namespace OCA\NextcloudTalkBot\AppInfo;

use OCA\NextcloudTalkBot\Controller\SettingsController;
use OCA\NextcloudTalkBot\Controller\WebhookController;
use OCA\NextcloudTalkBot\Service\BotService;
use OCA\NextcloudTalkBot\Service\MessageService;
use OCA\NextcloudTalkBot\Service\SettingsService;
use OCA\NextcloudTalkBot\Service\SignatureService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'nextcloudtalkbot';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Services are auto-wired by Nextcloud's DI container
        // No manual registration needed for most services
    }

    public function boot(IBootContext $context): void {
        // Boot logic if needed
    }
}
