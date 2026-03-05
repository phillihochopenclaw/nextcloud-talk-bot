<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Service;

use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Service for verifying webhook signatures
 * 
 * Implements HMAC-based signature verification for secure webhook endpoints.
 * Supports multiple signature algorithms and timing-safe comparison.
 */
class SignatureService
{
    private const CONFIG_PREFIX = 'nextcloudtalkbot';
    
    /** @var string SHA256 algorithm identifier */
    public const ALGO_SHA256 = 'sha256';
    
    /** @var string SHA512 algorithm identifier */
    public const ALGO_SHA512 = 'sha512';
    
    /** @var int Maximum allowed timestamp drift in seconds */
    private const MAX_TIMESTAMP_DRIFT = 300; // 5 minutes

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly IConfig $config
    ) {}

    /**
     * Verify a webhook signature
     * 
     * @param string $payload The raw request body
     * @param string $signature The signature from headers
     * @param string $timestamp The timestamp from headers
     * @param string $secret The webhook secret
     * @param string $algorithm The signature algorithm (default: sha256)
     * @return bool True if signature is valid
     */
    public function verify(
        string $payload,
        string $signature,
        string $timestamp,
        string $secret,
        string $algorithm = self::ALGO_SHA256
    ): bool {
        // 1. Check timestamp to prevent replay attacks
        if (!$this->verifyTimestamp($timestamp)) {
            $this->logger->warning('Webhook rejected: timestamp drift too large', [
                'timestamp' => $timestamp,
                'current' => time()
            ]);
            return false;
        }
        
        // 2. Verify the signature
        $expectedSignature = $this->computeSignature($payload, $timestamp, $secret, $algorithm);
        
        // Use timing-safe comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('Webhook rejected: invalid signature', [
                'algorithm' => $algorithm
            ]);
            return false;
        }
        
        $this->logger->debug('Webhook signature verified successfully');
        return true;
    }

    /**
     * Compute the expected signature for a payload
     * 
     * Format: HMAC-SHA256(secret, timestamp + '.' + payload)
     * 
     * @param string $payload The raw request body
     * @param string $timestamp The timestamp
     * @param string $secret The webhook secret
     * @param string $algorithm The hash algorithm
     * @return string The hex-encoded signature
     */
    public function computeSignature(
        string $payload,
        string $timestamp,
        string $secret,
        string $algorithm = self::ALGO_SHA256
    ): string {
        $signedPayload = $timestamp . '.' . $payload;
        
        return hash_hmac($algorithm, $signedPayload, $secret);
    }

    /**
     * Verify timestamp is within acceptable drift
     * 
     * @param string $timestamp The timestamp to verify
     * @return bool True if timestamp is valid
     */
    private function verifyTimestamp(string $timestamp): bool
    {
        if (!ctype_digit($timestamp)) {
            return false;
        }
        
        $timestampInt = (int) $timestamp;
        $currentTime = time();
        $drift = abs($currentTime - $timestampInt);
        
        return $drift <= self::MAX_TIMESTAMP_DRIFT;
    }

    /**
     * Get the webhook secret for a room
     * 
     * @param string $roomId The Talk room ID
     * @return string|null The secret or null if not configured
     */
    public function getSecret(string $roomId): ?string
    {
        $secret = $this->config->getAppValue(self::CONFIG_PREFIX, "webhook_secret_{$roomId}", '');
        
        return $secret !== '' ? $secret : null;
    }

    /**
     * Set the webhook secret for a room
     * 
     * @param string $roomId The Talk room ID
     * @param string $secret The webhook secret
     */
    public function setSecret(string $roomId, string $secret): void
    {
        $this->config->setAppValue(self::CONFIG_PREFIX, "webhook_secret_{$roomId}", $secret);
        $this->logger->info('Webhook secret configured', ['room_id' => $roomId]);
    }

    /**
     * Generate a new secure webhook secret
     * 
     * @return string The generated secret (64 hex characters)
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get the configured signature algorithm
     * 
     * @return string The algorithm identifier
     */
    public function getAlgorithm(): string
    {
        $algorithm = $this->config->getAppValue(
            self::CONFIG_PREFIX, 
            'signature_algorithm', 
            self::ALGO_SHA256
        );
        
        $validAlgorithms = [self::ALGO_SHA256, self::ALGO_SHA512];
        
        if (!in_array($algorithm, $validAlgorithms, true)) {
            return self::ALGO_SHA256;
        }
        
        return $algorithm;
    }

    /**
     * Set the signature algorithm
     * 
     * @param string $algorithm The algorithm to use
     * @throws \InvalidArgumentException If algorithm is not supported
     */
    public function setAlgorithm(string $algorithm): void
    {
        $validAlgorithms = [self::ALGO_SHA256, self::ALGO_SHA512];
        
        if (!in_array($algorithm, $validAlgorithms, true)) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported algorithm "%s". Must be one of: %s',
                    $algorithm,
                    implode(', ', $validAlgorithms)
                )
            );
        }
        
        $this->config->setAppValue(self::CONFIG_PREFIX, 'signature_algorithm', $algorithm);
    }

    /**
     * Extract signature from HTTP headers
     * 
     * Supports multiple header formats:
     * - X-Webhook-Signature
     * - X-Signature
     * - Signature
     * 
     * @param array<string, string> $headers The request headers
     * @return string|null The signature or null if not found
     */
    public function extractSignature(array $headers): ?string
    {
        // Normalize header names
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        
        $headerNames = [
            'x-webhook-signature',
            'x-signature',
            'signature'
        ];
        
        foreach ($headerNames as $name) {
            if (isset($normalizedHeaders[$name])) {
                // Remove 'sha256=' prefix if present
                $signature = $normalizedHeaders[$name];
                if (str_starts_with(strtolower($signature), 'sha256=')) {
                    return substr($signature, 7);
                }
                if (str_starts_with(strtolower($signature), 'sha512=')) {
                    return substr($signature, 7);
                }
                return $signature;
            }
        }
        
        return null;
    }

    /**
     * Extract timestamp from HTTP headers
     * 
     * @param array<string, string> $headers The request headers
     * @return string|null The timestamp or null if not found
     */
    public function extractTimestamp(array $headers): ?string
    {
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        
        $headerNames = [
            'x-webhook-timestamp',
            'x-timestamp',
            'timestamp'
        ];
        
        foreach ($headerNames as $name) {
            if (isset($normalizedHeaders[$name])) {
                return $normalizedHeaders[$name];
            }
        }
        
        return null;
    }
}