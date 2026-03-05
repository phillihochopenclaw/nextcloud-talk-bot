<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Service;

use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Service for managing bot configuration and operations
 */
class BotService
{
    private const CONFIG_PREFIX = 'nextcloudtalkbot';
    
    /** @var string Bot is active and responding */
    public const STATUS_ACTIVE = 'active';
    
    /** @var string Bot is paused and not responding */
    public const STATUS_PAUSED = 'paused';
    
    /** @var string Bot is in maintenance mode */
    public const STATUS_MAINTENANCE = 'maintenance';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly IConfig $config
    ) {}

    /**
     * Get the bot's display name
     * 
     * @return string The bot name
     */
    public function getBotName(): string
    {
        return $this->config->getAppValue(self::CONFIG_PREFIX, 'bot_name', 'Talk Bot');
    }

    /**
     * Set the bot's display name
     * 
     * @param string $name The new bot name
     */
    public function setBotName(string $name): void
    {
        $this->config->setAppValue(self::CONFIG_PREFIX, 'bot_name', $name);
    }

    /**
     * Get the bot's current status
     * 
     * @return string One of STATUS_* constants
     */
    public function getStatus(): string
    {
        return $this->config->getAppValue(self::CONFIG_PREFIX, 'status', self::STATUS_ACTIVE);
    }

    /**
     * Set the bot's status
     * 
     * @param string $status One of STATUS_* constants
     * @throws \InvalidArgumentException If status is invalid
     */
    public function setStatus(string $status): void
    {
        $validStatuses = [self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_MAINTENANCE];
        
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid status "%s". Must be one of: %s', 
                    $status, 
                    implode(', ', $validStatuses)
                )
            );
        }
        
        $this->config->setAppValue(self::CONFIG_PREFIX, 'status', $status);
        $this->logger->info('Bot status changed', ['status' => $status]);
    }

    /**
     * Check if the bot is active and can respond
     * 
     * @return bool True if bot is active
     */
    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    /**
     * Get allowed webhook source IPs
     * 
     * @return array<int, string> List of allowed IP addresses/ranges
     */
    public function getAllowedSourceIps(): array
    {
        $ips = $this->config->getAppValue(self::CONFIG_PREFIX, 'allowed_ips', '');
        
        if (empty($ips)) {
            return []; // Empty = all IPs allowed
        }
        
        return array_map('trim', explode(',', $ips));
    }

    /**
     * Set allowed webhook source IPs
     * 
     * @param array<int, string> $ips List of IP addresses/ranges
     */
    public function setAllowedSourceIps(array $ips): void
    {
        $this->config->setAppValue(self::CONFIG_PREFIX, 'allowed_ips', implode(',', $ips));
    }

    /**
     * Check if an IP address is allowed to send webhooks
     * 
     * @param string $clientIp The client IP to check
     * @return bool True if allowed
     */
    public function isIpAllowed(string $clientIp): bool
    {
        $allowedIps = $this->getAllowedSourceIps();
        
        if (empty($allowedIps)) {
            return true; // No restrictions = all allowed
        }
        
        return $this->matchIp($clientIp, $allowedIps);
    }

    /**
     * Match an IP against a list of allowed IPs/ranges
     * 
     * @param string $clientIp The client IP
     * @param array<int, string> $allowedIps Allowed IPs/ranges
     * @return bool True if match found
     */
    private function matchIp(string $clientIp, array $allowedIps): bool
    {
        foreach ($allowedIps as $allowed) {
            // Simple exact match for now
            // TODO: Support CIDR notation (e.g., 192.168.1.0/24)
            if ($clientIp === $allowed) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the bot's response prefix for messages
     * 
     * @return string The prefix (e.g., "🤖 ")
     */
    public function getMessagePrefix(): string
    {
        return $this->config->getAppValue(self::CONFIG_PREFIX, 'message_prefix', '🤖 ');
    }

    /**
     * Generate a unique webhook token for a room
     * 
     * @param string $roomId The Talk room ID
     * @return string The generated token
     */
    public function generateWebhookToken(string $roomId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->config->setAppValue(self::CONFIG_PREFIX, "webhook_token_{$roomId}", $token);
        
        $this->logger->info('Generated webhook token', ['room_id' => $roomId]);
        
        return $token;
    }

    /**
     * Get the webhook token for a room
     * 
     * @param string $roomId The Talk room ID
     * @return string|null The token or null if not set
     */
    public function getWebhookToken(string $roomId): ?string
    {
        $token = $this->config->getAppValue(self::CONFIG_PREFIX, "webhook_token_{$roomId}", '');
        
        return $token !== '' ? $token : null;
    }

    /**
     * Revoke a webhook token for a room
     * 
     * @param string $roomId The Talk room ID
     */
    public function revokeWebhookToken(string $roomId): void
    {
        $this->config->deleteAppValue(self::CONFIG_PREFIX, "webhook_token_{$roomId}");
        $this->logger->info('Revoked webhook token', ['room_id' => $roomId]);
    }
}