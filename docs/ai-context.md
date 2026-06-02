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

- AutoCrud models, separated from normal Eloquent models
- model tables, fillable attributes, casts, AutoCrud fields and detected relations
- database tables, columns and foreign keys
- Inertia routes, controllers, pages and detected render props
- Vue pages and components with `defineProps`, `defineEmits` and component usage

You can write to a different file if needed:

```bash
php artisan ai:context --path=docs/AGENTS.md
```

To preview the generated section without writing a file:

```bash
php artisan ai:context --stdout
```
