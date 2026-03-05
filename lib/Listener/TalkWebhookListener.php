<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Listener;

use OCA\NextcloudTalkBot\Service\BotService;
use OCA\NextcloudTalkBot\Service\MessageService;
use OCA\NextcloudTalkBot\Service\SignatureService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Talk\Events\AParticipantCreatedEvent;
use OCP\Talk\Events\AMessageSentEvent;
use Psr\Log\LoggerInterface;

/**
 * Listener for Nextcloud Talk webhook events
 * 
 * Handles incoming messages and participant events for bot processing
 */
class TalkWebhookListener implements IEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BotService $botService,
        private readonly SignatureService $signatureService,
        private readonly MessageService $messageService
    ) {}

    /**
     * Register this listener with the event dispatcher
     */
    public static function register(IEventDispatcher $dispatcher): void
    {
        $dispatcher->addListener(AMessageSentEvent::class, static function (Event $event): void {
            // Handle message events
        });
        
        $dispatcher->addListener(AParticipantCreatedEvent::class, static function (Event $event): void {
            // Handle participant join events
        });
    }

    /**
     * Handle incoming Talk events
     * 
     * @param Event $event The dispatched event
     */
    public function handle(Event $event): void
    {
        try {
            if ($event instanceof AMessageSentEvent) {
                $this->handleMessage($event);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to handle Talk event', [
                'exception' => $e,
                'event' => get_class($event)
            ]);
        }
    }

    /**
     * Process an incoming message event
     * 
     * @param AMessageSentEvent $event The message event
     */
    private function handleMessage(AMessageSentEvent $event): void
    {
        $message = $event->getMessage();
        $participant = $event->getParticipant();
        
        // Skip if not a bot command
        if (!$this->isBotCommand($message->getMessage())) {
            return;
        }
        
        // Process the command
        $this->processCommand(
            $message->getMessage(),
            $message->getRoom(),
            $participant
        );
    }

    /**
     * Check if a message is a bot command
     * 
     * @param string $message The message text
     * @return bool True if it's a bot command
     */
    private function isBotCommand(string $message): bool
    {
        return str_starts_with($message, '!') || str_starts_with($message, '/bot ');
    }

    /**
     * Process a bot command
     * 
     * @param string $command The command string
     * @param mixed $room The Talk room
     * @param mixed $participant The sender
     */
    private function processCommand(string $command, mixed $room, mixed $participant): void
    {
        // TODO: Implement command processing
        $this->logger->debug('Processing bot command', [
            'command' => $command,
            'room' => method_exists($room, 'getId') ? $room->getId() : 'unknown'
        ]);
    }
}