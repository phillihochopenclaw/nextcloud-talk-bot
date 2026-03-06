# Nextcloud Talk Bot

Ein KI-gestützter Bot für Nextcloud Talk.

## Agent System Prompts

Dieses Repository enthält System Prompts für ein KI-Scrum-Team:

- [PO-Agent](agents/po/SYSTEM_PROMPT.md) - Product Owner
- [Dev-Agent](agents/dev/SYSTEM_PROMPT.md) - Developer
- [Tester-Agent](agents/tester/SYSTEM_PROMPT.md) - QA/Tester
- [Reviewer-Agent](agents/reviewer/SYSTEM_PROMPT.md) - Code Reviewer
- [Designer-Agent](agents/designer/SYSTEM_PROMPT.md) - UI/UX Designer

## Agent Worktrees

Jeder Agent arbeitet in seinem eigenen Git Worktree:
- `agent/po` → Worktree: `projects/nextcloud-talk-bot-po/`
- `agent/dev` → Worktree: `projects/nextcloud-talk-bot-dev/`
- `agent/tester` → Worktree: `projects/nextcloud-talk-bot-tester/`
- `agent/reviewer` → Worktree: `projects/nextcloud-talk-bot-reviewer/`
- `agent/designer` → Worktree: `projects/nextcloud-talk-bot-designer/`
