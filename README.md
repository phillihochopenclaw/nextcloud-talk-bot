# 🎙️ Nextcloud Talk Bot

> **Automatische Meeting-Aufzeichnung und Transkription für Nextcloud Talk**

---

## 📋 Projektbeschreibung

**Nextcloud Talk Bot** ist eine Nextcloud-App, die Meetings in Nextcloud Talk automatisch aufzeichnet, transkribiert und die Ergebnisse strukturiert aufbereitet – ähnlich wie [tl;dv](https://tldv.io), aber speziell für die Nextcloud-Ökosystem.

### Kernfunktionen

| Feature | Beschreibung |
|---------|--------------|
| 🔴 **Aufzeichnung** | Automatische Aufzeichnung von Audio/Video-Meetings in Nextcloud Talk |
| 📝 **Transkription** | KI-basierte Umwandlung von Sprache in Text (mehrsprachig) |
| 🔍 **Suche** | Durchsuchbare Transkripte mit Zeitstempeln |
| 📤 **Export** | Export als Text, PDF, oder Markdown |
| 🤖 **Zusammenfassung** | Automatische Meeting-Zusammenfassung mit Action Items |
| 👥 **Speaker-Diarisierung** | Unterscheidung zwischen verschiedenen Sprechern |

---

## 🎯 Zielgruppe

- Teams, die Nextcloud Talk für Meetings nutzen
- Organisationen, die Datensouveränität wahren wollen (keine externe Cloud)
- Unternehmen, die Meeting-Transkripte für Dokumentation/Compliance benötigen

---

## 🏗️ Architektur-Überblick

```
┌─────────────────────────────────────────────────────────────┐
│                     NEXTCLOUD TALK                          │
│                                                             │
│  ┌──────────────┐      ┌──────────────────────────────┐    │
│  │   Meeting    │◄────►│     Talk Bot (Dieses App)    │    │
│  │   Raum       │      │                              │    │
│  └──────────────┘      │  ┌──────────┐  ┌──────────┐  │    │
│                        │  │ Recorder │  │  Trans-  │  │    │
│                        │  │  Modul   │  │ criber   │  │    │
│                        │  └────┬─────┘  └────┬─────┘  │    │
│                        └───────┼─────────────┼────────┘    │
│                                │             │              │
│                                ▼             ▼              │
│                        ┌──────────────────────────┐        │
│                        │     Nextcloud Files      │        │
│                        │   (Aufzeichnungen +        │        │
│                        │    Transkripte)          │        │
│                        └──────────────────────────┘        │
└─────────────────────────────────────────────────────────────┘
```

---

## 🛠️ Technischer Stack

| Komponente | Technologie |
|------------|-------------|
| **Backend** | PHP (Nextcloud App Framework) |
| **Frontend** | Vue.js / JavaScript (Nextcloud Standard) |
| **Datenbank** | Nextcloud DB (MySQL/PostgreSQL) |
| **Transkription** | OpenAI Whisper API (oder lokales Modell) |
| **Speicher** | Nextcloud Files API |

---

## 📅 Projekt-Status

| Sprint | Status | Ziel |
|--------|--------|------|
| Sprint 1 | 🔄 **IN PLANUNG** | Grundstruktur & Setup |

---

## 👥 Autonomes Scrum-Team

Dieses Projekt wird von einem autonomen KI-Scrum-Team entwickelt:

| Rolle | Verantwortlichkeit |
|-------|-------------------|
| **PO** | Stakeholder-Kommunikation, Backlog-Management |
| **SM** | Prozess-Einhaltung, Moderation |
| **Architect** | System-Architektur, Security |
| **Frontend** | UI/UX, Nextcloud-Integration |
| **Backend** | API, Datenbank, Transkription-Engine |
| **QA** | Tests, Qualitätsmetriken |

---

## 📚 Dokumentation

- [Scrum Board](../scrum-board/board.md)
- [Product Backlog](../backlog/product-backlog.md)
- [Definition of Done](../docs/definition-of-done.md)

---

## 🤝 Stakeholder

**Ansprechpartner:** Philipp Hoch (Product Owner Proxy)

---

_Projekt gestartet: August 2025_
=======
# Nextcloud Talk Bot

A bot for Nextcloud Talk that responds to messages via webhooks.

## Features

- **Webhook Integration**: Receive and process messages from Nextcloud Talk
- **Echo Mode**: Bot echoes back messages (configurable)
- **Command Support**: Built-in commands (`/help`, `/ping`, `/status`)
- **Admin Settings**: Configure bot via Nextcloud admin interface

## Requirements

- Nextcloud 27+
- PHP 8.1+
- Nextcloud Talk app

## Installation

### From Release

1. Download the latest release
2. Extract to `apps/talk_bot/` in your Nextcloud installation
3. Enable the app: `php occ app:enable talk_bot`

### From Source

```bash
cd apps/
git clone https://github.com/openclaw/nextcloud-talk-bot.git talk_bot
cd talk_bot
composer install --no-dev
php occ app:enable talk_bot
```

## Configuration

### Admin Settings

Navigate to **Settings > Administration > Talk Bot** to configure:

- **Webhook URL**: The URL to receive Talk webhooks
- **Bot Token**: Authentication token for the bot
- **Echo Mode**: Enable/disable message echoing
- **Commands**: Enable/disable command processing
- **Response Prefix**: Emoji/text prefix for bot responses

### Commands

| Command | Description |
|---------|-------------|
| `/help` | Show available commands |
| `/ping` | Ping the bot (responds with Pong) |
| `/status` | Show bot status |

## Development

### Setup

```bash
git clone https://github.com/openclaw/nextcloud-talk-bot.git
cd nextcloud-talk-bot
composer install
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run unit tests only
./vendor/bin/phpunit --testsuite Unit

# Run integration tests only
./vendor/bin/phpunit --testsuite Integration

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Code Quality

```bash
# PHP CodeSniffer
./vendor/bin/phpcs --standard=PSR12 lib/ tests/

# Fix coding standards
./vendor/bin/phpcbf --standard=PSR12 lib/ tests/

# PHPStan
./vendor/bin/phpstan analyse lib/ tests/
```

## API Reference

### Webhook Endpoint

**POST** `/apps/talk_bot/webhook`

Receives messages from Nextcloud Talk.

**Request Body:**
```json
{
    "message": "Hello Bot!",
    "user": "username",
    "conversation": "conversation-id",
    "timestamp": 1709500000
}
```

**Response:**
```json
{
    "status": "success",
    "result": {
        "action": "reply",
        "message": "🤖 Echo: Hello Bot!",
        "metadata": {
            "original_user": "username",
            "processed_at": "2024-03-04T08:00:00+00:00"
        }
    }
}
```

### Health Check

**GET** `/apps/talk_bot/webhook/health`

Returns bot health status.

**Response:**
```json
{
    "status": "ok",
    "timestamp": 1709500000,
    "version": "1.0.0"
}
```

### Settings API

**GET** `/apps/talk_bot/settings/admin`

Get admin settings (requires admin privileges).

**POST** `/apps/talk_bot/settings/admin`

Update admin settings (requires admin privileges).

## Architecture

```
talk_bot/
├── appinfo/
│   ├── info.xml         # App metadata
│   ├── routes.php       # Route definitions
│   └── services.xml     # Dependency injection
├── lib/
│   ├── AppInfo/
│   │   └── Application.php
│   ├── Controller/
│   │   ├── WebhookController.php
│   │   └── SettingsController.php
│   └── Service/
│       └── MessageService.php
├── tests/
│   ├── Framework/       # Test utilities
│   ├── Unit/            # Unit tests
│   └── Integration/     # Integration tests
└── docs/
    └── TEST_STRATEGY.md # Testing documentation
```

## Testing

This project follows a comprehensive testing strategy:

- **Unit Tests**: 80%+ code coverage target
- **Integration Tests**: App structure and configuration
- **Test Framework**: PHPUnit 10.x with custom mocks

See [docs/TEST_STRATEGY.md](docs/TEST_STRATEGY.md) for details.

## License

AGPL-3.0-or-later

## Authors

OpenClaw Team - openclaw@philipp-hoch.de

