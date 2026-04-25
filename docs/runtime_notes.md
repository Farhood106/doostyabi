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

## Predefined availability + goals in MVP onboarding

- Availability step is selection-based (no manual `weekday/start_minute/end_minute` entry in UI).
- User selects weekday checkboxes + friendly time blocks; backend maps to `availability_slots` with `timezone_name = Asia/Tehran`.
- `weekend` time block maps to Iran weekend-friendly days (`Thursday` + `Friday`) for MVP.
- Goals step remains DB-driven (`goals` table active items) but is rendered in user-friendly grouped sections with short descriptions.
- Goal saving still uses existing `user_goals` flow and marks profile completed after successful submit.

## Onboarding persistence/readback behavior

- Every onboarding step loads saved DB values when revisited:
  - Profile from `profiles` (with `region_code -> province` and `location_cell_l5 -> city` reconstruction)
  - Boundaries from `profile_boundaries`
  - Availability from `availability_slots` (reverse-mapped to UI day/block selections)
  - Goals from active rows in `user_goals`
- Prefill priority is: failed-submit session input (`_old`) > saved DB values > defaults.

## Match card generation dependency

- Dashboard cards are not generated synchronously on onboarding submit.
- In shared-hosting deployments, schedule both jobs:
  - `php bin/cron_generate_candidates.php`
  - `php bin/cron_build_match_cards.php`
- Until those run, dashboard can legitimately show no cards.

## No-match state uniqueness + cron safety (MySQL/MariaDB)

- `no_match_states` keeps unique key `uq_no_match_active_scope (user_id, goal_scope_key, is_active)`.
- In MySQL/MariaDB this means only one inactive row can coexist per scope too.
- Candidate generation now prunes old inactive rows in the same scope before deactivating active rows, preventing duplicate-key (`1062`) during cron.
- MVP tradeoff: no-match history is reduced to one inactive row per scope.

### One-time cleanup for existing deployments (safe before cron)

```sql
-- Keep newest inactive row per (user_id, goal_scope_key), delete older inactive duplicates.
DELETE n1
FROM no_match_states n1
JOIN no_match_states n2
  ON n1.user_id = n2.user_id
 AND n1.goal_scope_key = n2.goal_scope_key
 AND n1.is_active = 0
 AND n2.is_active = 0
 AND n1.id < n2.id;
```
