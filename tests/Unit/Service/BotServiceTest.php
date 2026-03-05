<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Tests\Unit\Service;

use OCA\NextcloudTalkBot\Service\BotService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\NextcloudTalkBot\Service\BotService
 */
class BotServiceTest extends TestCase
{
    private BotService $service;
    private LoggerInterface&MockObject $logger;
    private IConfig&MockObject $config;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(IConfig::class);
        $this->service = new BotService($this->logger, $this->config);
    }

    public function testGetBotNameDefault(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'bot_name', 'Talk Bot')
            ->willReturn('Talk Bot');
        
        $name = $this->service->getBotName();
        
        $this->assertEquals('Talk Bot', $name);
    }

    public function testGetBotNameCustom(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'bot_name', 'Talk Bot')
            ->willReturn('Custom Bot');
        
        $name = $this->service->getBotName();
        
        $this->assertEquals('Custom Bot', $name);
    }

    public function testSetBotName(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('nextcloudtalkbot', 'bot_name', 'New Bot Name');
        
        $this->service->setBotName('New Bot Name');
    }

    public function testGetStatusDefault(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'status', BotService::STATUS_ACTIVE)
            ->willReturn(BotService::STATUS_ACTIVE);
        
        $status = $this->service->getStatus();
        
        $this->assertEquals(BotService::STATUS_ACTIVE, $status);
    }

    public function testSetStatusValid(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('nextcloudtalkbot', 'status', BotService::STATUS_PAUSED);
        
        $this->service->setStatus(BotService::STATUS_PAUSED);
    }

    public function testSetStatusInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status');
        
        $this->service->setStatus('invalid_status');
    }

    public function testIsActive(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'status', BotService::STATUS_ACTIVE)
            ->willReturn(BotService::STATUS_ACTIVE);
        
        $this->assertTrue($this->service->isActive());
    }

    public function testIsNotActive(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'status', BotService::STATUS_ACTIVE)
            ->willReturn(BotService::STATUS_PAUSED);
        
        $this->assertFalse($this->service->isActive());
    }

    public function testGetAllowedSourceIpsEmpty(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'allowed_ips', '')
            ->willReturn('');
        
        $ips = $this->service->getAllowedSourceIps();
        
        $this->assertEmpty($ips);
    }

    public function testGetAllowedSourceIpsConfigured(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'allowed_ips', '')
            ->willReturn('192.168.1.1, 10.0.0.1');
        
        $ips = $this->service->getAllowedSourceIps();
        
        $this->assertEquals(['192.168.1.1', '10.0.0.1'], $ips);
    }

    public function testIsIpAllowedWhenNoRestrictions(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'allowed_ips', '')
            ->willReturn('');
        
        $this->assertTrue($this->service->isIpAllowed('any.ip.address'));
    }

    public function testIsIpAllowedWhenInWhitelist(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'allowed_ips', '')
            ->willReturn('192.168.1.1,10.0.0.1');
        
        $this->assertTrue($this->service->isIpAllowed('192.168.1.1'));
    }

    public function testIsIpDeniedWhenNotInWhitelist(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'allowed_ips', '')
            ->willReturn('192.168.1.1,10.0.0.1');
        
        $this->assertFalse($this->service->isIpAllowed('8.8.8.8'));
    }

    public function testGenerateWebhookToken(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with($this->callback(function (string $key): bool {
                return str_starts_with($key, 'webhook_token_');
            }));
        
        $token = $this->service->generateWebhookToken('room-123');
        
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testGetWebhookToken(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'webhook_token_room-123', '')
            ->willReturn('existing-token');
        
        $token = $this->service->getWebhookToken('room-123');
        
        $this->assertEquals('existing-token', $token);
    }

    public function testGetWebhookTokenNotSet(): void
    {
        $this->config->method('getAppValue')
            ->with('nextcloudtalkbot', 'webhook_token_room-123', '')
            ->willReturn('');
        
        $token = $this->service->getWebhookToken('room-123');
        
        $this->assertNull($token);
    }

    public function testRevokeWebhookToken(): void
    {
        $this->config->expects($this->once())
            ->method('deleteAppValue')
            ->with('nextcloudtalkbot', 'webhook_token_room-123');
        
        $this->service->revokeWebhookToken('room-123');
    }
}