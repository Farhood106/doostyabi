# Auth + Onboarding Foundation (Plain PHP, Shared Hosting)

## Suggested folder structure

```text
bootstrap/
  app.php
  autoload.php
config/
  app.php
  database.php
public/
  index.php
routes/
  web.php
src/
  Auth/
  Controllers/
  Core/
  Database/
  I18n/
  Repositories/
  Security/
  Support/
  Validation/
views/
  layouts/
  auth/
  onboarding/
  dashboard.php
database/
  schema.sql
  design_notes.md
docs/
  app_foundation.md
  sql_assumptions.md
storage/
  cache/
```

## Bootstrap and entry flow

1. `public/index.php` is front controller.
2. It loads `bootstrap/app.php`.
3. Bootstrap initializes:
   - config arrays
   - PHP session
   - `App` container instance
   - middleware closures (guest/auth)
   - routes
4. `Router::dispatch()` resolves route and controller action.

## How this maps to schema

- `users` + `AuthService` + `UserRepository`: registration/login/logout.
- `users.preferred_locale`/`ui_direction` + `Translator` + `locales`/`i18n_texts`: Persian-first RTL UI with future bilingual support.
- `profiles`, `profile_boundaries`, `availability_slots`, `user_goals`: onboarding persistence through `OnboardingRepository`.
- `goals` (key-based fields) used in onboarding goal selection without hardcoded UI strings.

## Shared-hosting fit

- No frameworks, no queue workers, no sockets.
- PDO only, session auth, CSRF token in session.
- One entry point and simple autoload suitable for cPanel-like hosting.

## Next integration points

- Add onboarding completion flag and route guard.
- Add reveal flow controllers for `reveal_requests`/`reveal_consents`.
- Add matching cron scripts using `match_candidate_queue`.
