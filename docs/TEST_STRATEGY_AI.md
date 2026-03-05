# Test Strategy - AI Integration (Sprint 2)

## Overview

This document defines the testing strategy for AI integration in Sprint 2. It covers all AI-related services including provider detection, API integration, and security considerations.

## AI Services Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        AI Provider Layer                             │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                   AIProviderDetector                          │   │
│  │  - Detect available providers (Nextcloud AI, OpenAI, Custom)  │   │
│  │  - Priority-based fallback chain                              │   │
│  │  - Provider configuration management                          │   │
│  └───────────────────────────┬──────────────────────────────────┘   │
│                              │                                       │
│  ┌───────────────────────────▼──────────────────────────────────┐   │
│  │                    Provider Services                            │   │
│  │  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ │   │
│  │  │ NextcloudAI     │ │ OpenAI          │ │ CustomAI        │ │   │
│  │  │ Service         │ │ Service         │ │ Service         │ │   │
│  │  └────────┬────────┘ └────────┬────────┘ └────────┬────────┘ │   │
│  │           │                   │                   │          │   │
│  └───────────┼───────────────────┼───────────────────┼──────────┘   │
│              │                   │                   │              │
│  ┌───────────▼───────────────────▼───────────────────▼──────────┐   │
│  │                    Config Services                             │   │
│  │  ┌─────────────────────────────────────────────────────────┐ │   │
│  │  │ OpenAIConfigService  │  SettingsService                 │ │   │
│  │  └─────────────────────────────────────────────────────────┘ │   │
│  └───────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

## Test Categories

### 1. Unit Tests

**Location:** `tests/Unit/Service/`

#### 1.1 AIProviderDetectorTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testDetectProviderNone` | No AI enabled returns `PROVIDER_NONE` | High |
| `testDetectProviderNextcloudAI` | Nextcloud AI detection when available | High |
| `testDetectProviderOpenAI` | OpenAI detection when API key configured | High |
| `testDetectProviderCustom` | Custom provider detection | High |
| `testPriorityOrder` | Nextcloud AI > OpenAI > Custom priority | High |
| `testExplicitProviderSetting` | Respects explicit provider config | Medium |
| `testAutoDetectNextcloudAI` | Auto-detect TextProcessing availability | Medium |
| `testAutoDetectOpenAI` | Auto-detect OpenAI API key | Medium |
| `testGetAvailableProviders` | Returns all available providers | Medium |
| `testGetCurrentProviderConfig` | Returns provider configuration | Medium |

**Mocking Requirements:**
- `IConfig` - for app configuration values
- `OpenAIConfigService` - for API key checks

#### 1.2 OpenAIConfigServiceTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testGetApiKey` | Retrieve stored API key | High |
| `testSetApiKey` | Store API key | High |
| `testHasApiKeyTrue` | Returns true when key exists | High |
| `testHasApiKeyFalse` | Returns false when key empty | High |
| `testDeleteApiKey` | Remove API key | High |
| `testGetDefaultModel` | Default model retrieval | Medium |
| `testSetDefaultModel` | Set default model | Medium |
| `testGetMaxTokens` | Max tokens retrieval | Medium |
| `testSetMaxTokens` | Set max tokens | Medium |
| `testGetTemperature` | Temperature retrieval | Medium |
| `testSetTemperature` | Set temperature | Medium |
| `testGetOrganizationId` | Organization ID retrieval | Low |
| `testGetAllSettings` | Get all settings with masked API key | Medium |
| `testApiKeyMasking` | API key is masked in getAllSettings | High |

#### 1.3 NextcloudAIServiceTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testIsAvailableTrue` | Returns true when providers exist | High |
| `testIsAvailableFalse` | Returns false when no providers | High |
| `testIsAvailableException` | Handles exceptions gracefully | Medium |
| `testGetAvailableTaskTypes` | Returns task types from providers | Medium |
| `testProcessChatSuccess` | Successful chat processing | High |
| `testProcessChatNoProvider` | Exception when no provider | High |
| `testProcessChatException` | Handles processing exceptions | Medium |
| `testCreateChatContext` | Creates chat context correctly | Medium |
| `testGetTaskStatus` | Returns task status | Low |
| `testGetTaskOutput` | Returns output for completed tasks | Low |
| `testProcessChatAsync` | Async task creation | Medium |

**Mocking Requirements:**
- `OCP\TextProcessing\IManager` - TextProcessing manager mock

#### 1.4 OpenAIServiceTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testChatSuccess` | Successful chat completion | High |
| `testChatNoApiKey` | Exception when no API key | High |
| `testChatWithHistory` | Chat with conversation history | Medium |
| `testChatRetryOnRateLimit` | Retry on 429 status | High |
| `testChatRetryOnServerError` | Retry on 5xx status | High |
| `testChatMaxRetriesExceeded` | Fails after max retries | High |
| `testChatInvalidResponse` | Handles invalid response format | Medium |
| `testChatErrorResponse` | Parses error response | Medium |
| `testTestConnectionSuccess` | Connection test succeeds | Medium |
| `testTestConnectionFailure` | Connection test fails | Medium |
| `testListModels` | Lists available models | Low |
| `testBuildMessages` | Builds messages array correctly | Medium |

**Mocking Requirements:**
- `OCP\Http\Client\IClient` - HTTP client mock
- `OCP\Http\Client\IResponse` - HTTP response mock
- `OpenAIConfigService` - Config service mock

#### 1.5 CustomAIServiceTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testIsConfiguredTrue` | Returns true when endpoint set | High |
| `testIsConfiguredFalse` | Returns false when no endpoint | High |
| `testGetSetEndpoint` | Get/set endpoint URL | High |
| `testGetSetApiKey` | Get/set API key | High |
| `testGetSetModel` | Get/set model name | Medium |
| `testGetSetHeaders` | Get/set custom headers | Medium |
| `testGetSetTimeout` | Get/set timeout | Medium |
| `testChatSuccess` | Successful chat request | High |
| `testChatNoEndpoint` | Exception when no endpoint | High |
| `testChatRetryOnFailure` | Retry on server error | High |
| `testParseResponseOpenAI` | Parse OpenAI-compatible format | Medium |
| `testParseResponseCustom` | Parse custom response format | Medium |
| `testParseResponseRaw` | Return raw body if not JSON | Low |
| `testTestConnectionSuccess` | Connection test succeeds | Medium |
| `testTestConnectionFailure` | Connection test fails | Medium |
| `testBuildHeaders` | Build headers with auth | Medium |
| `testBuildPayload` | Build request payload correctly | Medium |

**Mocking Requirements:**
- `OCP\Http\Client\IClient` - HTTP client mock
- `OCP\Http\Client\IResponse` - HTTP response mock
- `IConfig` - Configuration mock

---

### 2. Integration Tests

**Location:** `tests/Integration/`

#### 2.1 AIProviderIntegrationTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testProviderDetectionWithRealConfig` | Detection with actual config values | High |
| `testFallbackChain` | Provider fallback order | High |
| `testConfigPersistence` | Settings persist across requests | Medium |

#### 2.2 APIKeyEncryptionTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testApiKeyStorage` | API key stored in config | High |
| `testApiKeyRetrieval` | API key retrieved correctly | High |
| `testApiKeyNotInLogs` | API key not exposed in logs | High |

---

### 3. Security Tests

**Location:** `tests/Unit/Service/Security/`

#### 3.1 APIKeySecurityTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testApiKeyNotLogged` | API key not in logger output | Critical |
| `testApiKeyMaskedInOutput` | API key masked in API responses | Critical |
| `testApiKeyNotInException` | API key not in exception messages | Critical |
| `testApiKeySanitization` | API key sanitized in debug output | High |

#### 3.2 InputSanitizationTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testSanitizePrompt` | Prompt sanitization | High |
| `testSanitizeConversationHistory` | History sanitization | Medium |
| `testXssPrevention` | XSS in prompts prevented | Critical |
| `testSqlInjectionPrevention` | SQL injection prevented | Critical |

#### 3.3 RateLimitingTest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testRateLimitBackoff` | Exponential backoff on rate limit | High |
| `testMaxRetries` | Max retries enforced | High |
| `testTimeoutHandling` | Request timeout handled | Medium |

---

### 4. E2E Tests

**Location:** `tests/E2E/` (future)

#### 4.1 AIResponseE2ETest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| `testBotRespondsWithAI` | Bot uses AI for response | High |
| `testFallbackToStatic` | Falls back to static response | High |
| `testSettingsPersistence` | Settings save and load | Medium |

---

## Mock Implementations

### MockTextProcessingManager

```php
class MockTextProcessingManager implements IManager {
    private bool $hasProviders = true;
    private array $providers = [];
    private array $responses = [];
    
    public function setHasProviders(bool $has): void {
        $this->hasProviders = $has;
    }
    
    public function hasProviders(): bool {
        return $this->hasProviders;
    }
    
    public function setMockResponse(string $prompt, string $response): void {
        $this->responses[$prompt] = $response;
    }
    
    // ... interface implementation
}
```

### MockHttpClient

```php
class MockHttpClient implements IClient {
    private array $responses = [];
    private int $callCount = 0;
    
    public function setMockResponse(string $url, int $status, string $body): void {
        $this->responses[$url] = ['status' => $status, 'body' => $body];
    }
    
    public function post(string $url, array $options = []): IResponse {
        $this->callCount++;
        if (isset($this->responses[$url])) {
            return new MockResponse($this->responses[$url]);
        }
        return new MockResponse(['status' => 500, 'body' => '']);
    }
    
    // ... interface implementation
}
```

---

## Coverage Targets

### Sprint 2 AI Services

| Service | Target | Notes |
|---------|--------|-------|
| AIProviderDetector | 95% | Critical for provider selection |
| OpenAIConfigService | 95% | API key management |
| NextcloudAIService | 90% | Core AI functionality |
| OpenAIService | 90% | HTTP client with retry logic |
| CustomAIService | 90% | HTTP client with custom formats |
| **Overall AI Coverage** | **90%** | Combined coverage |

### Security Coverage

| Area | Target | Notes |
|------|--------|-------|
| API Key Security | 100% | No key exposure |
| Input Sanitization | 95% | All injection vectors |
| Rate Limiting | 90% | Retry behavior |

---

## Running Tests

### All AI Tests

```bash
# Run all AI-related unit tests
./vendor/bin/phpunit --testsuite Unit --filter "AI\|OpenAI\|Custom"

# Run specific test file
./vendor/bin/phpunit tests/Unit/Service/AIProviderDetectorTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/ai tests/Unit/Service/AIProviderDetectorTest.php
```

### Security Tests Only

```bash
./vendor/bin/phpunit tests/Unit/Service/Security/
```

### Integration Tests

```bash
./vendor/bin/phpunit --testsuite Integration
```

---

## Test Data

### API Key Test Vectors

```php
// Valid API keys for testing
const VALID_OPENAI_KEY = 'sk-test-1234567890abcdef';
const VALID_CUSTOM_KEY = 'custom-api-key-12345';

// Invalid test cases
const EMPTY_KEY = '';
const SHORT_KEY = 'abc';
const SPECIAL_CHARS_KEY = 'key-with-@#$%';
```

### Response Test Vectors

```json
{
  "valid_openai_response": {
    "choices": [{
      "message": {
        "role": "assistant",
        "content": "This is a test response."
      }
    }]
  },
  "rate_limit_response": {
    "error": {
      "message": "Rate limit exceeded",
      "type": "rate_limit_error"
    }
  }
}
```

---

## Security Checklist

### API Key Handling

- [ ] API keys never logged in plaintext
- [ ] API keys masked in getAllSettings()
- [ ] API keys not in exception messages
- [ ] API keys sanitized before debug output
- [ ] API keys encrypted in storage (if applicable)

### Input Handling

- [ ] All prompts sanitized before API calls
- [ ] XSS prevention in conversation history
- [ ] Length limits enforced
- [ ] Invalid characters filtered

### Network Security

- [ ] HTTPS-only endpoints enforced
- [ ] Timeout limits set
- [ ] Rate limiting implemented
- [ ] Retry with exponential backoff
- [ ] Connection pooling (if available)

---

## Dependencies

### Test Dependencies

- PHPUnit 10.x
- PHP 8.1+
- Mock interfaces for Nextcloud APIs

### Mock Interfaces Needed

- `OCP\TextProcessing\IManager`
- `OCP\TextProcessing\Task`
- `OCP\Http\Client\IClient`
- `OCP\Http\Client\IResponse`
- `OCP\IConfig`

---

## Timeline

### Week 1: Unit Tests
- Day 1-2: AIProviderDetectorTest
- Day 3-4: OpenAIConfigServiceTest
- Day 5: NextcloudAIServiceTest

### Week 2: HTTP Services & Security
- Day 1-2: OpenAIServiceTest
- Day 3-4: CustomAIServiceTest
- Day 5: Security Tests

### Week 3: Integration & E2E
- Day 1-2: Integration tests
- Day 3-4: E2E tests
- Day 5: Coverage verification & documentation

---

## Conclusion

This test strategy ensures comprehensive coverage for Sprint 2 AI integration with:

1. **Unit Tests** - Fast, isolated tests for each service
2. **Integration Tests** - Provider detection and config persistence
3. **Security Tests** - API key protection, input sanitization, rate limiting
4. **E2E Tests** - Full user workflow verification

Target: 90%+ coverage for all AI services with critical security paths at 100%.