---
title: AI Context
description: Generate an AGENTS.md project context section for AI agents
---

# AI Context

The package registers an Artisan command that regenerates a project context section for AI tools:

```bash
php artisan ai:context
```

The command creates or updates `AGENTS.md` at the project root. It only replaces the section between these markers and leaves the rest of the file untouched:

```md
<!-- LARAVEL_AUTO_CRUD_AI_CONTEXT_START -->
...
<!-- LARAVEL_AUTO_CRUD_AI_CONTEXT_END -->
```

The generated section includes:

- agent instructions telling AI tools to use the `laravel-auto-crud` skill before CRUD-related changes
- a reminder to rerun `php artisan ai:context` after changes affecting models, schema, routes, Inertia pages or Vue components
- AutoCrud models, separated from normal Eloquent models
- model tables, fillable attributes, casts, AutoCrud fields and detected relations
- database tables, columns and foreign keys
- Inertia routes, controllers, pages and detected render props
- Vue pages and components with `defineProps`, `defineEmits` and component usage

## Recommended Agent Skill

For the best results, publish the package skill into the host project so AI agents know the Laravel AutoCrud conventions before reading the generated context.

For OpenCode-compatible agents:

```bash
php artisan vendor:publish --tag=laravel-auto-crud-skill --force
```

This publishes the skill to:

```txt
.opencode/skills/laravel-auto-crud/
```

For Claude Code-compatible agents:

```bash
php artisan vendor:publish --tag=laravel-auto-crud-skill-claude --force
```

This publishes the skill to:

```txt
.claude/skills/laravel-auto-crud/
```

Recommended workflow for AI-assisted work:

1. Publish the skill for your agent.
2. Run `php artisan ai:context` to update `AGENTS.md`.
3. Ask the agent to read `AGENTS.md` and use the `laravel-auto-crud` skill before making CRUD-related changes.

You can write to a different file if needed:

```bash
php artisan ai:context --path=docs/AGENTS.md
```

To preview the generated section without writing a file:

```bash
php artisan ai:context --stdout
```
