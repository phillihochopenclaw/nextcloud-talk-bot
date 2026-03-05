<?php
declare(strict_types=1);

namespace OCA\TalkBot\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'talk_bot';

	public function __construct(array $params = []) {
		parent::__construct(self::APP_ID, $params);
	}

	public function bootstrap(IRegistrationContext $context, IBootContext $bootContext): void {
		// Registration handled via appinfo/info.xml and services.xml
	}
}
