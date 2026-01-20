# ADR-001: Use Inertia.js with Vue 3

**Status:** Accepted
**Date:** 2025-11-26
**Deciders:** Development Team

## Context

We need to build a modern, responsive web application for the LMS. The options considered were:

1. **Traditional Blade templates** - Server-rendered HTML
2. **Vue SPA with API** - Separate frontend and backend
3. **Inertia.js with Vue** - Server-driven SPA

## Decision

We will use **Inertia.js v2 with Vue 3** for the frontend.

## Rationale

### Why Inertia.js?

| Factor | Blade Only | Vue SPA + API | Inertia + Vue |
|--------|------------|---------------|---------------|
| Development speed | Fast | Slow (API + Frontend) | Fast |
| SPA experience | No | Yes | Yes |
| SEO | Good | Requires SSR | Good |
| Authentication | Simple | Complex (tokens) | Simple (sessions) |
| Code duplication | Low | High (validation) | Low |
| Real-time updates | Limited | Yes | Yes |

### Key Benefits

1. **No API layer needed** - Controllers return Inertia responses directly
2. **Session-based auth** - Use Laravel's built-in auth, no tokens
3. **Shared validation** - Server-side validation, errors in Vue props
4. **Laravel conventions** - Middleware, policies, form requests work normally
5. **Progressive enhancement** - Links work without JavaScript

### Why Vue 3?

- Composition API for better code organization
- TypeScript support
- Excellent Inertia adapter
- Large ecosystem (Shadcn/ui Vue)

## Consequences

### Positive

- Faster development than building separate API
- Full Laravel ecosystem (Fortify, policies, middleware)
- Type-safe routes with Wayfinder
- Consistent validation between server and client

### Negative

- Tighter coupling between Laravel and Vue
- Less suitable for multiple clients (mobile apps need API)
- Team needs to learn Inertia patterns

### Risks

- **Inertia updates**: Major version changes may require refactoring
- **Mitigation**: Pin version, follow upgrade guides

## Alternatives Considered

### Vue SPA with REST API

Rejected because:
- Double the work (API + frontend)
- Duplicate validation
- Complex authentication (JWT/Sanctum tokens)
- API versioning overhead

### Livewire

Rejected because:
- Less flexible for complex UI
- Limited TypeScript support
- Smaller component ecosystem

## References

- [Inertia.js Documentation](https://inertiajs.com/)
- [Laravel Inertia Adapter](https://github.com/inertiajs/inertia-laravel)
- [Vue 3 Documentation](https://vuejs.org/)
