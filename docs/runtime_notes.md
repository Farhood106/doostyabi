# Runtime localization and template string policy

## How templates avoid hardcoded strings

- Templates use `t('...')` helper for user-facing copy.
- `t()` delegates to `Translator::get()` and resolves text keys from `i18n_texts`.
- Goal cards/onboarding goal labels use `goals.title_key` from DB and call `t($goal['title_key'])`.
- Validation/auth feedback store key names (e.g., `validation.required`, `auth.invalid_credentials`) so messages remain translatable.

## How RTL/lang are applied at runtime

- During login/register, auth layer sets session locale/direction from user fields (`preferred_locale`, `ui_direction`).
- Helpers `currentLocale()` and `currentDirection()` read session runtime state.
- Main layout applies `<html lang="..." dir="...">` using those helpers.
- `config/app.php` defaults locale to `fa`; app defaults direction to `rtl` when no user session exists.

## Main-flow i18n seed patch (shared-hosting deployments)

- Base schema includes minimal bilingual examples; run `database/i18n_seed_main_flow.sql` after schema import to upsert complete Persian+English keys used in auth/onboarding/dashboard/match/chat/reveal/validation/common flows.
