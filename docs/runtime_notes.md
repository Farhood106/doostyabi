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
- Cron scripts run in pure CLI mode and do **not** start PHP sessions.
- Optional debug mode for candidate cron:
  - `php bin/cron_generate_candidates.php 50 0 200 --verbose`
  - prints user id, candidate ids, hard-filter decision, queue writes, and caught exceptions.

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

## PDO HY093 guardrails for native prepares

- Production/shared-hosting PDO may run with `PDO::ATTR_EMULATE_PREPARES = false`.
- Under native prepares, reusing the same named placeholder multiple times in a single SQL statement can raise `SQLSTATE[HY093]: Invalid parameter number`.
- Repository queries are written with unique placeholder names per occurrence (e.g., `:uid_a`, `:uid_b`) even when bound to the same runtime value.
- Verification fixtures force native behavior and include a static SQL placeholder audit:
  - `php bin/verify_pdo_placeholder_safety.php`

## Chat UX principles (MVP)

- Chat page is intentionally pressure-free: no online status, typing indicator, or seen/read receipts.
- Main conversation area appears first; progressive reveal is secondary and collapsible.
- Starter prompts are privacy-safe and currently static i18n keys; they can evolve into goal-driven/admin-managed suggestions later.

## Notification lifecycle (phase 1)

- Notifications are generated in service/business logic (not in templates).
- Event rules:
  - `strong_match_available`: emitted when match-card builder creates/refreshes a card with strong score (currently `>= 82`).
  - `mutual_interest_created`: emitted for both participants when mutual interest opens secure chat.
  - `reveal_request_received`: emitted to the other participant on request creation.
  - `reveal_request_accepted` / `reveal_request_declined`: emitted to requester on response.
  - `reveal_request_cancelled`: emitted to other participant when requester cancels.
  - `reveal_request_expired`: emitted when pending request expires during panel load/check.
  - `new_message_received` (lightweight scaffold): emitted to the counterpart on message send.
- Dashboard loads recent notifications + unread count and allows mark-read / mark-all-read.
- Notification dedupe scope (MVP): `(user_id, template_id, entity_type, entity_id)` for entity-bound events.
- Strong-match emission rule: only on new card above threshold or threshold-crossing from below to above (not on every refresh).

## Goal-aware starter prompt strategy (phase 1)

- Starter prompt selection is goal-cluster aware (emotional / activity / collaboration / general fallback).
- Prompts are key-based i18n text entries (Persian-first), safe for admin-management in later phases.
- Prompts are privacy-safe:
  - no exact identity disclosure,
  - no extracted private profile facts,
  - no pressure features (seen/typing/online).
- On chat open, system inserts up to 2 prompt messages once (duplicate-safe via existing prompt-message check).

## Counterpart dedupe in dashboard cards (MVP)

- Delivery query suppresses near-identical duplicates and returns only the best card per counterpart user for each viewer.
- Self-match rows are explicitly excluded.
- If multiple goal-specific matches exist for the same counterpart, highest score/newest card wins for dashboard display.

### Optional cleanup SQL (existing duplicate notifications/cards)

```sql
-- Keep newest notification per dedupe scope for strong match notifications.
DELETE n1
FROM notifications n1
JOIN notifications n2
  ON n1.user_id = n2.user_id
 AND n1.template_id = n2.template_id
 AND IFNULL(n1.entity_type, '') = IFNULL(n2.entity_type, '')
 AND IFNULL(n1.entity_id, 0) = IFNULL(n2.entity_id, 0)
 AND n1.id < n2.id
JOIN notification_templates t
  ON t.id = n1.template_id
WHERE t.template_key = 'notification.strong_match_available';

-- Keep best card per viewer/counterpart pair (by score, updated_at, id).
DELETE mc_old
FROM match_cards mc_old
JOIN matches m_old ON m_old.id = mc_old.match_id
JOIN match_cards mc_new
  ON mc_new.viewer_user_id = mc_old.viewer_user_id
JOIN matches m_new ON m_new.id = mc_new.match_id
WHERE (CASE WHEN m_old.user_a_id = mc_old.viewer_user_id THEN m_old.user_b_id ELSE m_old.user_a_id END) =
      (CASE WHEN m_new.user_a_id = mc_new.viewer_user_id THEN m_new.user_b_id ELSE m_new.user_a_id END)
  AND (
       mc_new.compatibility_score > mc_old.compatibility_score
       OR (mc_new.compatibility_score = mc_old.compatibility_score AND mc_new.updated_at > mc_old.updated_at)
       OR (mc_new.compatibility_score = mc_old.compatibility_score AND mc_new.updated_at = mc_old.updated_at AND mc_new.id > mc_old.id)
  );
```

## Onboarding flow (guided, goal-driven MVP)

- Guided step order:
  1) basic profile (identity basics, age/birth-year, location, interest and preferred age range),
  2) main goal selection,
  3) goal-specific question set for selected goals.
- Goal-specific questions are item-based (select/radio) and persisted in dynamic tables:
  - definitions: `goal_preference_definitions`,
  - user answers: `user_goal_preferences`.
- Current implementation validates required goal-specific answers and restores saved values on revisit.

## Boundary storage decision (bug fix)

- Root cause: schema uniqueness was `(user_id, boundary_key)`, but product allows multiple items in same boundary category (same `boundary_key`, different `boundary_value`).
- Decision: use unique key `(user_id, boundary_key, boundary_value)` to keep category grouping and allow multiple selections safely.
- Repository write path now uses transaction-wrapped replace to avoid partial writes on failure.

### One-time migration for existing deployments

- If your database was created before this fix, apply:
  - `database/migrations/2026_04_26_fix_profile_boundaries_unique_key.sql`
- This migration updates the unique index on `profile_boundaries` and prevents duplicate-key failures when users choose multiple boundary items under one category.

## Next matching phase for goal answers

- Current phase stores and reads goal-specific preferences reliably.
- Goal-specific fit is now included in compatibility scoring (`goal_specific_fit`) with neutral handling for missing data.
- Score breakdown now includes:
  - `goal_specific_fit`
  - `shared_goal_keys`
  - `preference_matches`
  - `preference_gaps`

## Goal-specific question step (phase 2)

- Onboarding flow is now:
  1) profile
  2) boundaries
  3) availability
  4) goals
  5) goal-questions
- New routes:
  - `GET /onboarding/goal-questions`
  - `POST /onboarding/goal-questions`
- MVP behavior for multi-goal users:
  - UI renders question catalog for **primary selected goal** (first active goal by priority),
  - persistence format remains compatible with multi-goal (`goal_pref[goal_id][pref_key]`).

## Goal-question catalog design

- Catalog logic is centralized in `GoalQuestionCatalogService`.
- Cluster mapping is slug-based and uses stable `pref_key` identifiers.
- Definitions remain DB-driven through `goal_preference_definitions` (admin-ready path).
