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
