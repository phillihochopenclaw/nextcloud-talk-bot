<?php
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Integration;

use OCA\TalkBot\Tests\Framework\TestCase;

/**
 * Integration tests for App Installation
 * 
 * These tests verify that the app structure is correct and
 * that it follows Nextcloud app conventions.
 */
class AppInstallTest extends TestCase {

	/**
	 * Test that appinfo directory exists
	 */
	public function testAppInfoDirectoryExists(): void {
		$appInfoDir = dirname(__DIR__, 2) . '/appinfo';
		$this->assertDirectoryExists($appInfoDir, 'appinfo directory should exist');
	}

	/**
	 * Test that info.xml exists and is valid
	 */
	public function testInfoXmlExists(): void {
		$infoFile = dirname(__DIR__, 2) . '/appinfo/info.xml';
		$this->assertFileExists($infoFile, 'info.xml should exist');
		
		$content = file_get_contents($infoFile);
		$this->assertNotEmpty($content);
		
		// Verify it's valid XML
		$xml = simplexml_load_string($content);
		$this->assertInstanceOf(\SimpleXMLElement::class, $xml);
	}

	/**
	 * Test info.xml required elements
	 */
	public function testInfoXmlRequiredElements(): void {
		$infoFile = dirname(__DIR__, 2) . '/appinfo/info.xml';
		$content = file_get_contents($infoFile);
		$xml = simplexml_load_string($content);
		
		// Check required elements
		$this->assertEquals('talk_bot', (string) $xml->id);
		$this->assertNotEmpty((string) $xml->name);
		$this->assertNotEmpty((string) $xml->version);
		$this->assertEquals('AGPL-3.0-or-later', (string) $xml->license);
	}

	/**
	 * Test info.xml PHP version requirement
	 */
	public function testInfoXmlPhpRequirement(): void {
		$infoFile = dirname(__DIR__, 2) . '/appinfo/info.xml';
		$content = file_get_contents($infoFile);
		$xml = simplexml_load_string($content);
		
		$this->assertNotEmpty($xml->dependencies->php['min-version']);
		$minPhp = (string) $xml->dependencies->php['min-version'];
		$this->assertGreaterThanOrEqual('8.1', $minPhp, 'PHP version should be at least 8.1');
	}

	/**
	 * Test info.xml Nextcloud requirement
	 */
	public function testInfoXmlNextcloudRequirement(): void {
		$infoFile = dirname(__DIR__, 2) . '/appinfo/info.xml';
		$content = file_get_contents($infoFile);
		$xml = simplexml_load_string($content);
		
		$this->assertNotEmpty($xml->require->nextcloud['min-version']);
		$minNc = (string) $xml->require->nextcloud['min-version'];
		$this->assertGreaterThanOrEqual('27', $minNc, 'Nextcloud version should be at least 27');
	}

	/**
	 * Test that Application class exists
	 */
	public function testApplicationClassExists(): void {
		$this->assertTrue(
			class_exists(\OCA\TalkBot\AppInfo\Application::class),
			'Application class should exist'
		);
	}

	/**
	 * Test that Application class has correct app ID
	 */
	public function testApplicationClassAppId(): void {
		$reflection = new \ReflectionClass(\OCA\TalkBot\AppInfo\Application::class);
		$constant = $reflection->getConstant('APP_ID');
		
		$this->assertEquals('talk_bot', $constant, 'APP_ID constant should be talk_bot');
	}

	/**
	 * Test that routes file exists
	 */
	public function testRoutesFileExists(): void {
		$routesFile = dirname(__DIR__, 2) . '/appinfo/routes.php';
		$this->assertFileExists($routesFile, 'routes.php should exist');
	}

	/**
	 * Test that routes are defined correctly
	 */
	public function testRoutesDefined(): void {
		$routesFile = dirname(__DIR__, 2) . '/appinfo/routes.php';
		$routes = include $routesFile;
		
		$this->assertIsArray($routes);
		$this->assertArrayHasKey('routes', $routes);
		$this->assertIsArray($routes['routes']);
		$this->assertNotEmpty($routes['routes']);
	}

	/**
	 * Test that services.xml exists
	 */
	public function testServicesFileExists(): void {
		$servicesFile = dirname(__DIR__, 2) . '/appinfo/services.xml';
		$this->assertFileExists($servicesFile, 'services.xml should exist');
	}

	/**
	 * Test that lib directory exists
	 */
	public function testLibDirectoryExists(): void {
		$libDir = dirname(__DIR__, 2) . '/lib';
		$this->assertDirectoryExists($libDir, 'lib directory should exist');
	}

	/**
	 * Test that Controller directory exists
	 */
	public function testControllerDirectoryExists(): void {
		$controllerDir = dirname(__DIR__, 2) . '/lib/Controller';
		$this->assertDirectoryExists($controllerDir, 'Controller directory should exist');
	}

	/**
	 * Test that Service directory exists
	 */
	public function testServiceDirectoryExists(): void {
		$serviceDir = dirname(__DIR__, 2) . '/lib/Service';
		$this->assertDirectoryExists($serviceDir, 'Service directory should exist');
	}

	/**
	 * Test that WebhookController class exists
	 */
	public function testWebhookControllerExists(): void {
		$this->assertTrue(
			class_exists(\OCA\TalkBot\Controller\WebhookController::class),
			'WebhookController class should exist'
		);
	}

	/**
	 * Test that MessageService class exists
	 */
	public function testMessageServiceExists(): void {
		$this->assertTrue(
			class_exists(\OCA\TalkBot\Service\MessageService::class),
			'MessageService class should exist'
		);
	}

	/**
	 * Test that SettingsController class exists
	 */
	public function testSettingsControllerExists(): void {
		$this->assertTrue(
			class_exists(\OCA\TalkBot\Controller\SettingsController::class),
			'SettingsController class should exist'
		);
	}

	/**
	 * Test composer.json exists
	 */
	public function testComposerJsonExists(): void {
		$composerFile = dirname(__DIR__, 2) . '/composer.json';
		$this->assertFileExists($composerFile, 'composer.json should exist');
	}

	/**
	 * Test composer.json has correct namespace
	 */
	public function testComposerJsonNamespace(): void {
		$composerFile = dirname(__DIR__, 2) . '/composer.json';
		$composer = json_decode(file_get_contents($composerFile), true);
		
		$this->assertArrayHasKey('autoload', $composer);
		$this->assertArrayHasKey('psr-4', $composer['autoload']);
		$this->assertArrayHasKey('OCA\\TalkBot\\', $composer['autoload']['psr-4']);
		$this->assertEquals('lib/', $composer['autoload']['psr-4']['OCA\\TalkBot\\']);
	}

	/**
	 * Test composer.json has test namespace
	 */
	public function testComposerJsonTestNamespace(): void {
		$composerFile = dirname(__DIR__, 2) . '/composer.json';
		$composer = json_decode(file_get_contents($composerFile), true);
		
		$this->assertArrayHasKey('autoload-dev', $composer);
		$this->assertArrayHasKey('psr-4', $composer['autoload-dev']);
		$this->assertArrayHasKey('OCA\\TalkBot\\Tests\\', $composer['autoload-dev']['psr-4']);
		$this->assertEquals('tests/', $composer['autoload-dev']['psr-4']['OCA\\TalkBot\\Tests\\']);
	}

	/**
	 * Test composer.json has PHPUnit dependency
	 */
	public function testComposerJsonHasPHPUnit(): void {
		$composerFile = dirname(__DIR__, 2) . '/composer.json';
		$composer = json_decode(file_get_contents($composerFile), true);
		
		$this->assertArrayHasKey('require-dev', $composer);
		$this->assertArrayHasKey('phpunit/phpunit', $composer['require-dev']);
	}

	/**
	 * Test phpunit.xml exists
	 */
	public function testPHPUnitConfigExists(): void {
		$phpunitFile = dirname(__DIR__, 2) . '/phpunit.xml';
		$this->assertFileExists($phpunitFile, 'phpunit.xml should exist');
	}

	/**
	 * Test tests directory exists
	 */
	public function testTestsDirectoryExists(): void {
		$testsDir = dirname(__DIR__, 2) . '/tests';
		$this->assertDirectoryExists($testsDir, 'tests directory should exist');
	}

	/**
	 * Test tests bootstrap file exists
	 */
	public function testTestsBootstrapExists(): void {
		$bootstrapFile = dirname(__DIR__, 2) . '/tests/bootstrap.php';
		$this->assertFileExists($bootstrapFile, 'tests/bootstrap.php should exist');
	}

	/**
	 * Test that all classes follow PSR-4 autoloading
	 */
	public function testClassesFollowPsr4(): void {
		$libDir = dirname(__DIR__, 2) . '/lib';
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($libDir, \RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$relativePath = str_replace($libDir . '/', '', $file->getPathname());
				$className = 'OCA\\TalkBot\\' . str_replace(
					['/', '.php'],
					['\\', ''],
					$relativePath
				);
				
				$this->assertTrue(
					class_exists($className) || interface_exists($className) || trait_exists($className),
					"Class $className should be autoloadable from {$file->getPathname()}"
				);
			}
		}
	}

	/**
	 * Test PHP version compatibility
	 */
	public function testPhpVersionCompatible(): void {
		$this->assertGreaterThanOrEqual(
			'8.1.0',
			PHP_VERSION,
			'PHP version should be at least 8.1.0'
		);
	}
}