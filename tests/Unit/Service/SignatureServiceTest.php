<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Tests\Unit\Service;

use OCA\NextcloudTalkBot\Service\SignatureService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\NextcloudTalkBot\Service\SignatureService
 */
class SignatureServiceTest extends TestCase
{
    private SignatureService $service;
    private LoggerInterface&MockObject $logger;
    private IConfig&MockObject $config;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(IConfig::class);
        $this->service = new SignatureService($this->logger, $this->config);
    }

    public function testComputeSignatureWithSha256(): void
    {
        $payload = '{"test": "data"}';
        $timestamp = '1234567890';
        $secret = 'test-secret';
        
        $signature = $this->service->computeSignature($payload, $timestamp, $secret);
        
        $this->assertEquals(64, strlen($signature));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $signature);
    }

    public function testComputeSignatureIsDeterministic(): void
    {
        $payload = '{"test": "data"}';
        $timestamp = '1234567890';
        $secret = 'test-secret';
        
        $signature1 = $this->service->computeSignature($payload, $timestamp, $secret);
        $signature2 = $this->service->computeSignature($payload, $timestamp, $secret);
        
        $this->assertEquals($signature1, $signature2);
    }

    public function testDifferentPayloadsDifferentSignatures(): void
    {
        $payload1 = '{"test": "data1"}';
        $payload2 = '{"test": "data2"}';
        $timestamp = '1234567890';
        $secret = 'test-secret';
        
        $signature1 = $this->service->computeSignature($payload1, $timestamp, $secret);
        $signature2 = $this->service->computeSignature($payload2, $timestamp, $secret);
        
        $this->assertNotEquals($signature1, $signature2);
    }

    public function testVerifyValidSignature(): void
    {
        $payload = '{"test": "data"}';
        $timestamp = (string) time();
        $secret = 'test-secret';
        
        $signature = $this->service->computeSignature($payload, $timestamp, $secret);
        
        $result = $this->service->verify($payload, $signature, $timestamp, $secret);
        
        $this->assertTrue($result);
    }

    public function testVerifyInvalidSignature(): void
    {
        $payload = '{"test": "data"}';
        $timestamp = (string) time();
        $secret = 'test-secret';
        $wrongSignature = 'a' . str_repeat('0', 63);
        
        $result = $this->service->verify($payload, $wrongSignature, $timestamp, $secret);
        
        $this->assertFalse($result);
    }

    public function testVerifyExpiredTimestamp(): void
    {
        $payload = '{"test": "data"}';
        // Timestamp from 10 minutes ago (beyond the 5-minute drift)
        $timestamp = (string) (time() - 600);
        $secret = 'test-secret';
        
        $signature = $this->service->computeSignature($payload, $timestamp, $secret);
        
        $result = $this->service->verify($payload, $signature, $timestamp, $secret);
        
        $this->assertFalse($result);
    }

    public function testVerifyFutureTimestamp(): void
    {
        $payload = '{"test": "data"}';
        // Timestamp from 10 minutes in the future
        $timestamp = (string) (time() + 600);
        $secret = 'test-secret';
        
        $signature = $this->service->computeSignature($payload, $timestamp, $secret);
        
        $result = $this->service->verify($payload, $signature, $timestamp, $secret);
        
        $this->assertFalse($result);
    }

    public function testVerifyInvalidTimestampFormat(): void
    {
        $payload = '{"test": "data"}';
        $timestamp = 'not-a-number';
        $secret = 'test-secret';
        
        $result = $this->service->verify($payload, 'somesig', $timestamp, $secret);
        
        $this->assertFalse($result);
    }

    public function testGenerateSecret(): void
    {
        $secret = $this->service->generateSecret();
        
        $this->assertEquals(64, strlen($secret));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $secret);
    }

    public function testGenerateSecretIsRandom(): void
    {
        $secret1 = $this->service->generateSecret();
        $secret2 = $this->service->generateSecret();
        
        $this->assertNotEquals($secret1, $secret2);
    }

    public function testExtractSignatureFromHeaders(): void
    {
        $headers = [
            'X-Webhook-Signature' => 'abc123'
        ];
        
        $signature = $this->service->extractSignature($headers);
        
        $this->assertEquals('abc123', $signature);
    }

    public function testExtractSignatureWithPrefix(): void
    {
        $headers = [
            'X-Webhook-Signature' => 'sha256=abc123'
        ];
        
        $signature = $this->service->extractSignature($headers);
        
        $this->assertEquals('abc123', $signature);
    }

    public function testExtractSignatureFromAlternateHeader(): void
    {
        $headers = [
            'X-Signature' => 'xyz789'
        ];
        
        $signature = $this->service->extractSignature($headers);
        
        $this->assertEquals('xyz789', $signature);
    }

    public function testExtractSignatureNotFound(): void
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        $signature = $this->service->extractSignature($headers);
        
        $this->assertNull($signature);
    }

    public function testExtractTimestampFromHeaders(): void
    {
        $headers = [
            'X-Webhook-Timestamp' => '1234567890'
        ];
        
        $timestamp = $this->service->extractTimestamp($headers);
        
        $this->assertEquals('1234567890', $timestamp);
    }

    public function testExtractTimestampNotFound(): void
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        $timestamp = $this->service->extractTimestamp($headers);
        
        $this->assertNull($timestamp);
    }

    public function testSha512Algorithm(): void
    {
        $payload = '{"test": "data"}';
        $timestamp = (string) time();
        $secret = 'test-secret';
        
        $signature = $this->service->computeSignature(
            $payload, 
            $timestamp, 
            $secret, 
            SignatureService::ALGO_SHA512
        );
        
        // SHA-512 produces 128 hex characters
        $this->assertEquals(128, strlen($signature));
        
        $result = $this->service->verify(
            $payload, 
            $signature, 
            $timestamp, 
            $secret, 
            SignatureService::ALGO_SHA512
        );
        
        $this->assertTrue($result);
    }
}