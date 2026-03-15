<?php
declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ILogger;
use OCA\NextcloudTalkBot\Service\MessageService;

/**
 * Listener for Nextcloud Talk chat messages
 * Handles messages posted in Talk conversations
 */
class ChatMessageListener implements IEventListener {

	public function __construct(
		private readonly MessageService $messageService,
		private readonly ILogger $logger
	) {}

	/**
	 * Handle incoming chat message events
	 * 
	 * @param Event $event The event to handle
	 */
	public function handle(Event $event): void {
		// This would be a real Talk message event
		// For now, we log that the listener is registered
		$this->logger->debug('TalkBot: ChatMessageListener triggered', [
			' => get_class($event)
		]);

		// In'event_class a real implementation, we would:
		// 1. Extract message data from the event
		// 2. Process the message via MessageService
		// 3. Send a response back to the room
	}

	/**
	 * Check if this listener supports the given event
	 * 
	 * @param Event $event The event to check
	 * @return bool True if the event is supported
	 */
	public static function supportsEvent(Event $event): bool {
		// This would check for actual Talk message event classes
		// Example: \OCA\Talk\Events\ChatMessageEvent::class
		return true;
	}
}
