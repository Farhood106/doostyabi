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

## Iran-only MVP onboarding location mapping

- Profile form asks user for consumer-friendly `province` + `city` + `distance_radius_km`.
- Backend derives internal matching fields and does not expose them directly:
  - `country_code = IR`
  - `region_code = province`
  - `location_cell_l4 = province`
  - `location_cell_l5 = city`

## Predefined boundaries in MVP onboarding

- Boundaries step is now checklist-based (no free-text boundary key/value input).
- UI shows grouped user-friendly options; each selected option stores one row in `profile_boundaries`.
- Stored mapping is stable and machine-friendly:
  - `boundary_key` = group key (e.g. `privacy`, `safety`)
  - `boundary_value` = option key (e.g. `no_recording_without_consent`)
  - `importance` = one of `preferred` / `required` / `avoid`
- UX behavior:
  - Save requires at least one selected boundary option.
  - After successful save, onboarding redirects to the next incomplete step (typically `availability`).
- Custom boundary authoring can be added later via admin-managed catalog.
