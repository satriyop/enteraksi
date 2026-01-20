# ADR-002: Use Laravel Fortify for Authentication

**Status:** Accepted
**Date:** 2025-11-26
**Deciders:** Development Team

## Context

The LMS requires:
- User registration and login
- Password reset via email
- Email verification
- Two-factor authentication (2FA)
- Role-based access control

Options considered:
1. **Laravel Breeze** - Starter kit with Blade views
2. **Laravel Jetstream** - Full-featured with Livewire/Inertia
3. **Laravel Fortify** - Headless authentication backend
4. **Custom implementation** - Build from scratch

## Decision

We will use **Laravel Fortify** for authentication.

## Rationale

### Why Fortify?

| Factor | Breeze | Jetstream | Fortify | Custom |
|--------|--------|-----------|---------|--------|
| Headless (no views) | No | No | Yes | Yes |
| 2FA support | No | Yes | Yes | Build |
| Customizable views | Limited | Limited | Full | Full |
| Maintenance burden | Low | Low | Low | High |
| Team features | No | Yes | No | Build |

### Key Benefits

1. **Headless** - We control all views (Inertia/Vue)
2. **2FA included** - TOTP-based with recovery codes
3. **Customizable actions** - Override registration, password reset
4. **Rate limiting** - Built-in protection against brute force
5. **Email verification** - Ready to use

### Why Not Others?

**Breeze**: Has views, no 2FA
**Jetstream**: Opinionated UI, includes unnecessary team features
**Custom**: Time-consuming, security risks

## Implementation

### Enabled Features

```php
// config/fortify.php
'features' => [
    Features::registration(),
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::twoFactorAuthentication([
        'confirmPassword' => true,
        'confirm' => true,
    ]),
],
```

### Custom Views

Fortify routes render our Inertia components:

```php
// FortifyServiceProvider.php
Fortify::loginView(fn () => Inertia::render('auth/Login'));
Fortify::registerView(fn () => Inertia::render('auth/Register'));
```

### Custom Actions

```php
// app/Actions/Fortify/CreateNewUser.php
public function create(array $input): User
{
    return User::create([
        'role' => 'learner',  // Default role
        // ...
    ]);
}
```

## Consequences

### Positive

- Secure, battle-tested authentication
- 2FA without additional packages
- Clean separation (backend logic vs views)
- Easy to customize views

### Negative

- Must implement all views ourselves
- No built-in UI components
- Configuration can be confusing

### Risks

- **Fortify updates**: May change APIs
- **Mitigation**: Test after updates, pin version

## Alternatives Considered

### Laravel Jetstream

Rejected because:
- Includes team management (not needed)
- Opinionated about views
- Harder to customize design

### Custom Implementation

Rejected because:
- Security risks
- Time to implement
- Must handle edge cases (rate limiting, etc.)

## References

- [Laravel Fortify Documentation](https://laravel.com/docs/fortify)
- [Two-Factor Authentication](https://laravel.com/docs/fortify#two-factor-authentication)
