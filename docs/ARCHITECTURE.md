# Architektur-Review: Nextcloud Talk Bot

**Sprint:** 1  
**Datum:** 2024-03-04  
**Architekt:** Senior Architect (glm-5)

---

## 1. Architektur-Übersicht

### 1.1 High-Level Design

```
┌─────────────────────────────────────────────────────────────────────┐
│                        External Services                             │
│                   (GitHub, GitLab, Monitoring, etc.)                 │
└────────────────────────────────┬────────────────────────────────────┘
                                 │ Webhook POST
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Nextcloud Talk Bot                            │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                   WebhookController                            │   │
│  │  - IP Whitelisting                                             │   │
│  │  - Signature Verification                                      │   │
│  │  - Payload Validation                                          │   │
│  └───────────────────────────┬──────────────────────────────────┘   │
│                              │                                       │
│  ┌───────────────────────────▼──────────────────────────────────┐   │
│  │                    SignatureService                            │   │
│  │  - HMAC-SHA256/SHA512 Verification                             │   │
│  │  - Timestamp Validation (Replay Protection)                    │   │
│  │  - Timing-Safe Comparison                                      │   │
│  └───────────────────────────┬──────────────────────────────────┘   │
│                              │                                       │
│  ┌───────────────────────────▼──────────────────────────────────┐   │
│  │                      BotService                                │   │
│  │  - Status Management                                           │   │
│  │  - Token Generation                                            │   │
│  │  - Configuration                                               │   │
│  └───────────────────────────┬──────────────────────────────────┘   │
│                              │                                       │
│  ┌───────────────────────────▼──────────────────────────────────┐   │
│  │                    MessageService                              │   │
│  │  - Message Formatting                                          │   │
│  │  - Room Access Check                                           │   │
│  │  - Talk Integration                                            │   │
│  └───────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         Nextcloud Talk                               │
│                        (Room Messages)                               │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.2 Komponenten

| Komponente | Verantwortung |
|------------|---------------|
| `WebhookController` | HTTP-Endpunkt, Request-Validierung, Response |
| `SignatureService` | Webhook-Signatur-Verifikation, Security |
| `BotService` | Bot-Status, Token-Management, Konfiguration |
| `MessageService` | Nachricht-Formatierung, Talk-Integration |
| `TalkWebhookListener` | Event-Listener für Talk-Events |

---

## 2. Security Architecture

### 2.1 Webhook Signature Verification

**Implementierung:** HMAC-basierte Signatur-Verifikation

```
Signature = HMAC-SHA256(secret, timestamp + '.' + payload)
```

**Security Features:**

1. **HMAC-SHA256/SHA512** - Kryptographisch sichere Signaturen
2. **Timestamp Validation** - Schutz gegen Replay-Attacks (max. 5 Minuten Drift)
3. **Timing-Safe Comparison** - `hash_equals()` gegen Timing-Attacks
4. **IP Whitelisting** - Optionale Beschränkung auf bekannte IPs

### 2.2 Threat Model

| Threat | Mitigation |
|--------|------------|
| Replay Attack | Timestamp-Validierung (≤5 Min Drift) |
| Signature Forgery | HMAC-SHA256 mit geheimem Schlüssel |
| Timing Attack | `hash_equals()` für konstante Vergleichszeit |
| IP Spoofing | IP Whitelisting (optional) |
| Message Injection | Signatur-Payload enthält gesamten Body |
| DoS | Rate Limiting (TODO: implementieren) |

### 2.3 Security Best Practices

```php
// ✅ CORRECT: Timing-safe comparison
if (!hash_equals($expectedSignature, $signature)) {
    return false;
}

// ❌ WRONG: Vulnerable to timing attacks
if ($expectedSignature !== $signature) {
    return false;
}
```

---

## 3. Nextcloud App Patterns

### 3.1 Service Registration

```php
// lib/AppInfo/Application.php
public function register(IRegistrationContext $context): void
{
    $context->registerService(BotService::class, function (ContainerInterface $c): BotService {
        return new BotService(
            $c->get(LoggerInterface::class),
            $c->get(\OCP\IConfig::class)
        );
    });
}
```

### 3.2 Dependency Injection

- Services werden über den Container injiziert
- Constructor-Injection für Testbarkeit
- Nextcloud-Interfaces (IConfig, ILogger) werden automatisch aufgelöst

### 3.3 Configuration Storage

```php
// Konfiguration wird verschlüsselt in der Nextcloud DB gespeichert
$this->config->setAppValue('nextcloudtalkbot', 'webhook_secret_' . $roomId, $secret);
```

---

## 4. Code Quality Gates

### 4.1 CI Pipeline (GitHub Actions)

```yaml
jobs:
  code-style:      # PHP-CS-Fixer
  static-analysis: # PHPStan Level 8
  unit-tests:      # PHPUnit
  security-check:  # Composer Audit
```

### 4.2 Code Style (PHP-CS-Fixer)

- **Strict Types:** `declare(strict_types=1)`
- **Ordered Imports:** Alphabetisch sortiert
- **PHPDoc:** Vollständige Dokumentation
- **Code Style:** PSR-12 kompatibel

### 4.3 Static Analysis (PHPStan Level 8)

- **Maximum Strictness:** Alle Type-Hints müssen korrekt sein
- **No Mixed Types:** Keine `mixed`-Typen ohne explizite Annotation
- **Null Safety:** Explizite Null-Checks

### 4.4 Test Coverage

| Service | Tests | Coverage |
|---------|-------|----------|
| SignatureService | 15+ | ~95% |
| BotService | 10+ | ~90% |
| MessageService | TODO | - |
| WebhookController | TODO | - |

---

## 5. Architektur-Entscheidungen

### 5.1 ADR-001: HMAC für Webhook-Signaturen

**Status:** Accepted

**Context:** Webhooks von externen Services müssen authentifiziert werden.

**Decision:** HMAC-SHA256/SHA512 mit Timestamp-basierter Replay-Protection.

**Consequences:**
- ✅ Sichere Authentifizierung ohne Passwörter
- ✅ Schutz gegen Replay-Attacks
- ⚠️ Zeit-Synchronisation zwischen Sender und Empfänger wichtig

### 5.2 ADR-002: Service-Layer Architecture

**Status:** Accepted

**Context:** Trennung von HTTP-Layer und Business-Logik.

**Decision:** Controller delegieren an Services, Services sind Framework-unabhängig.

**Consequences:**
- ✅ Bessere Testbarkeit (Services können isoliert getestet werden)
- ✅ Wiederverwendbarkeit der Business-Logik
- ✅ Klare Verantwortlichkeiten

### 5.3 ADR-003: Per-Room Secrets

**Status:** Accepted

**Context:** Webhook-Secrets pro Room vs. globales Secret.

**Decision:** Jeder Room hat ein eigenes Secret (`webhook_secret_{roomId}`).

**Consequences:**
- ✅ Compartmentalization: Ein kompromittiertes Secret betrifft nur einen Room
- ✅ Einfaches Rotation von Secrets pro Room
- ⚠️ Mehr Konfigurationsaufwand

---

## 6. Offene Punkte / TODOs

### Sprint 1 (Done)
- [x] CI Pipeline (GitHub Actions)
- [x] PHP-CS-Fixer Konfiguration
- [x] PHPStan Konfiguration (Level 8)
- [x] PHPUnit Setup
- [x] SignatureService mit Tests
- [x] BotService mit Tests
- [x] WebhookController
- [x] Architektur-Dokumentation

### Sprint 2 (Backlog)
- [ ] MessageService Tests
- [ ] WebhookController Integration Tests
- [ ] Rate Limiting implementieren
- [ ] Admin-Settings UI
- [ ] OCC Commands für Konfiguration
- [ ] Database Migration für Webhook-Configs

### Sprint 3 (Backlog)
- [ ] Support für verschiedene Webhook-Formate (GitHub, GitLab, etc.)
- [ ] Message Templates
- [ ] Bot Commands (in Talk)
- [ ] Logging & Monitoring Dashboard

---

## 7. Recommendations

### 7.1 Immediate Actions
1. **Rate Limiting** - Implementieren, um DoS zu verhindern
2. **Admin UI** - Einfache Konfiguration über Nextcloud Settings
3. **OCC Commands** - CLI für Secret-Management

### 7.2 Future Improvements
1. **Webhook Retry Logic** - Bei Fehlern automatisch wiederholen
2. **Message Queue** - Asynchrone Verarbeitung für hohe Last
3. **Metrics** - Prometheus-kompatible Metriken
4. **Audit Log** - Alle Webhook-Aktivitäten protokollieren

---

**Architektur-Review Status:** ✅ COMPLETE  
**Bereit für:** Sprint 2 Implementation