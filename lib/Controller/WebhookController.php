<?php

declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Controller;

use OCA\NextcloudTalkBot\AppInfo\Application;
use OCA\NextcloudTalkBot\Service\BotService;
use OCA\NextcloudTalkBot\Service\MessageService;
use OCA\NextcloudTalkBot\Service\SignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Controller for handling incoming webhooks
 */
class WebhookController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly LoggerInterface $logger,
        private readonly BotService $botService,
        private readonly SignatureService $signatureService,
        private readonly MessageService $messageService
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Handle incoming webhook POST request
     * 
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * 
     * @param string $roomId The Talk room ID
     * @return JSONResponse HTTP response
     */
    public function handleWebhook(string $roomId): JSONResponse
    {
        // 1. Check if bot is active
        if (!$this->botService->isActive()) {
            $this->logger->warning('Webhook rejected: bot not active');
            return new JSONResponse([
                'error' => 'Bot is not active'
            ], Http::STATUS_SERVICE_UNAVAILABLE);
        }
        
        // 2. Verify IP whitelist (if configured)
        $clientIp = $this->request->getRemoteAddress();
        if (!$this->botService->isIpAllowed($clientIp)) {
            $this->logger->warning('Webhook rejected: IP not whitelisted', [
                'client_ip' => $clientIp
            ]);
            return new JSONResponse([
                'error' => 'Access denied'
            ], Http::STATUS_FORBIDDEN);
        }
        
        // 3. Get the raw payload
        $payload = $this->request->getParams();
        $rawBody = file_get_contents('php://input');
        
        // 4. Verify signature
        $headers = $this->request->getHeaders();
        $signature = $this->signatureService->extractSignature($headers);
        $timestamp = $this->signatureService->extractTimestamp($headers);
        
        if ($signature === null || $timestamp === null) {
            $this->logger->warning('Webhook rejected: missing signature or timestamp');
            return new JSONResponse([
                'error' => 'Missing signature or timestamp'
            ], Http::STATUS_UNAUTHORIZED);
        }
        
        // Get secret for this room
        $secret = $this->signatureService->getSecret($roomId);
        if ($secret === null) {
            $this->logger->warning('Webhook rejected: no secret configured', [
                'room_id' => $roomId
            ]);
            return new JSONResponse([
                'error' => 'Webhook not configured'
            ], Http::STATUS_NOT_FOUND);
        }
        
        // Verify the signature
        $algorithm = $this->signatureService->getAlgorithm();
        if (!$this->signatureService->verify($rawBody, $signature, $timestamp, $secret, $algorithm)) {
            $this->logger->warning('Webhook rejected: invalid signature', [
                'room_id' => $roomId
            ]);
            return new JSONResponse([
                'error' => 'Invalid signature'
            ], Http::STATUS_UNAUTHORIZED);
        }
        
        // 5. Parse and validate the payload
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            $this->logger->warning('Webhook rejected: invalid JSON');
            return new JSONResponse([
                'error' => 'Invalid JSON payload'
            ], Http::STATUS_BAD_REQUEST);
        }
        
        // 6. Process the webhook
        try {
            $result = $this->processWebhook($roomId, $data);
            
            $this->logger->info('Webhook processed successfully', [
                'room_id' => $roomId
            ]);
            
            return new JSONResponse([
                'success' => true,
                'message' => 'Webhook processed',
                'result' => $result
            ], Http::STATUS_OK);
            
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process webhook', [
                'room_id' => $roomId,
                'exception' => $e
            ]);
            
            return new JSONResponse([
                'error' => 'Failed to process webhook'
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Process a validated webhook payload
     * 
     * @param string $roomId The target room ID
     * @param array<string, mixed> $data The webhook data
     * @return array<string, mixed> Processing result
     */
    private function processWebhook(string $roomId, array $data): array
    {
        // Extract message details from webhook payload
        $source = $data['source'] ?? 'Webhook';
        $title = $data['title'] ?? 'Notification';
        $body = $data['body'] ?? $data['message'] ?? '';
        $metadata = $data['metadata'] ?? [];
        
        // Send the message
        $success = $this->messageService->sendWebhookMessage(
            $roomId,
            $source,
            $title,
            $body,
            $metadata
        );
        
        return [
            'sent' => $success,
            'source' => $source
        ];
    }

    /**
     * Health check endpoint
     * 
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * 
     * @return JSONResponse Health status
     */
    public function health(): JSONResponse
    {
        return new JSONResponse([
            'status' => 'ok',
            'bot_status' => $this->botService->getStatus(),
            'version' => Application::APP_ID
        ], Http::STATUS_OK);
    }
}