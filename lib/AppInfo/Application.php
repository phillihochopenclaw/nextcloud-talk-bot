<?php
/**
 * Nextcloud Talk Bot - Application Bootstrap
 */
declare(strict_types=1);

namespace OCA\TalkBot\AppInfo;

use OCA\TalkBot\Controller\SettingsController;
use OCA\TalkBot\Service\SettingsService;
use OCP\AppFramework\IAppContainer;
use OCP\IConfig;
use OCP\IRequest;

class Application {

    public function __construct(
        private IAppContainer $container
    ) {
        $this->register();
    }

    private function register(): void {
        // Register Settings Service
        $this->container->registerService(SettingsService::class, function (IAppContainer $c) {
            return new SettingsService(
                $c->get(IConfig::class)
            );
        });

        // Register Settings Controller
        $this->container->registerService(SettingsController::class, function (IAppContainer $c) {
            return new SettingsController(
                $c->get('AppName'),
                $c->get(IRequest::class),
                $c->get(IConfig::class),
                $c->get(SettingsService::class)
            );
        });
    }
}
