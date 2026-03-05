<?php

declare(strict_types=1);

/**
 * Nextcloud Talk Bot Routes
 */

return [
    'routes' => [
        // Webhook endpoint
        [
            'name' => 'webhook#handleWebhook',
            'url' => '/webhook/{roomId}',
            'verb' => 'POST',
        ],
        // Health check
        [
            'name' => 'webhook#health',
            'url' => '/health',
            'verb' => 'GET',
        ],
        // Admin settings endpoints (Sprint 2: KI-Integration)
        [
            'name' => 'settings#getAdmin',
            'url' => '/settings/admin',
            'verb' => 'GET',
        ],
        [
            'name' => 'settings#setAdmin',
            'url' => '/settings/admin',
            'verb' => 'POST',
        ],
        // AI connection test (US-008)
        [
            'name' => 'settings#testAIConnection',
            'url' => '/settings/test-ai',
            'verb' => 'POST',
        ],
        // AI status (US-010)
        [
            'name' => 'settings#getAIStatus',
            'url' => '/settings/ai-status',
            'verb' => 'GET',
        ],
        // Set active provider (US-007, US-008)
        [
            'name' => 'settings#setActiveProvider',
            'url' => '/settings/active-provider',
            'verb' => 'POST',
        ],
    ],
];