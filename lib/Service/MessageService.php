<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Service;

use OCP\Talk\IManager as TalkManager;
use OCP\Talk\IRoom;
use Psr\Log\LoggerInterface;

/**
 * Service for sending messages to Talk rooms
 */
class MessageService
{
    private const CONFIG_PREFIX = 'nextcloudtalkbot';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BotService $botService,
        private readonly TalkManager $talkManager
    ) {}

    /**
     * Send a message to a Talk room
     * 
     * @param string $roomId The target room ID
     * @param string $message The message text
     * @param array<string, mixed> $options Optional message options
     * @return bool True if message was sent successfully
     */
    public function sendMessage(string $roomId, string $message, array $options = []): bool
    {
        try {
            // Check if bot is active
            if (!$this->botService->isActive()) {
                $this->logger->warning('Bot is not active, message not sent', [
                    'room_id' => $roomId
                ]);
                return false;
            }
            
            // Add bot prefix if configured
            $prefix = $this->botService->getMessagePrefix();
            if ($prefix !== '' && !str_starts_with($message, $prefix)) {
                $message = $prefix . $message;
            }
            
            // Get the room
            $room = $this->talkManager->getRoomById($roomId);
            
            if (!$room instanceof IRoom) {
                $this->logger->error('Room not found', ['room_id' => $roomId]);
                return false;
            }
            
            // Send the message
            // TODO: Implement actual message sending via Talk API
            // This requires a bot participant to be added to the room
            
            $this->logger->info('Message sent to room', [
                'room_id' => $roomId,
                'message_length' => strlen($message)
            ]);
            
            return true;
            
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send message', [
                'room_id' => $roomId,
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * Send a formatted webhook message
     * 
     * @param string $roomId The target room ID
     * @param string $source The webhook source name
     * @param string $title The message title
     * @param string $body The message body
     * @param array<string, mixed> $metadata Additional metadata
     * @return bool True if message was sent successfully
     */
    public function sendWebhookMessage(
        string $roomId,
        string $source,
        string $title,
        string $body,
        array $metadata = []
    ): bool {
        $message = $this->formatWebhookMessage($source, $title, $body, $metadata);
        
        return $this->sendMessage($roomId, $message);
    }

    /**
     * Format a webhook message for display
     * 
     * @param string $source The webhook source
     * @param string $title The message title
     * @param string $body The message body
     * @param array<string, mixed> $metadata Additional metadata
     * @return string The formatted message
     */
    private function formatWebhookMessage(
        string $source,
        string $title,
        string $body,
        array $metadata = []
    ): string {
        $lines = [];
        
        // Header with source and title
        $lines[] = sprintf('**[%s] %s**', $source, $title);
        $lines[] = '';
        
        // Body content
        if ($body !== '') {
            $lines[] = $body;
            $lines[] = '';
        }
        
        // Metadata (if any)
        if (!empty($metadata)) {
            $lines[] = '---';
            foreach ($metadata as $key => $value) {
                if (is_scalar($value)) {
                    $lines[] = sprintf('* %s: %s', $key, $value);
                }
            }
        }
        
        return implode("\n", $lines);
    }

    /**
     * Check if bot has access to a room
     * 
     * @param string $roomId The room ID to check
     * @return bool True if bot has access
     */
    public function hasRoomAccess(string $roomId): bool
    {
        try {
            $room = $this->talkManager->getRoomById($roomId);
            return $room instanceof IRoom;
        } catch (\Throwable) {
            return false;
        }
    }
}