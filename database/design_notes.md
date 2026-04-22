# Revised Design Notes (Privacy-First Compatibility Platform MVP)

## Changelog (what changed and why)

1. **Added `revealable_profile_data` table**
   - Added dedicated reveal-only storage for `first_name`, `photo_media_key`, `contact_payload_json`, and `deep_profile_payload_json`.
   - Kept this data separate from baseline matching profile to enforce progressive disclosure boundaries.

2. **Replaced `birth_date` with `birth_year` in `profiles`**
   - Reduced sensitivity of stored age data while still enabling age compatibility and server-side age-band calculations.

3. **Upgraded approximate location model**
   - Replaced simple `city_code` with privacy-preserving, coarse location dimensions:
     - `country_code`
     - `region_code`
     - `location_cell_l5`
     - `location_cell_l4`
   - Enables better distance/proximity heuristics without storing exact coordinates.

4. **Made `no_match_states` active-state uniqueness explicit**
   - Added `is_active` and stored `goal_scope_key` (application-maintained).
   - Added unique key `uq_no_match_active_scope (user_id, goal_scope_key, is_active)` to avoid ambiguous current state.

5. **Enforced one closure feedback per user per match**
   - Added unique key `uq_closure_feedback_match_user (match_id, submitted_by_user_id)`.

6. **Resolved moderation ambiguity in reports**
   - Removed ambiguous `resolved_by_admin_id` for MVP.
   - Added `resolution_note` and retained `status/resolved_at` lifecycle; admin identity can be added later with proper moderator domain model.

7. **Optional enhancement: message metadata/moderation scaffolding**
   - Added `messages.metadata_json` and `messages.moderation_state`.

8. **Optional enhancement: notification preferences**
   - Added `notification_preferences` table with per-category controls and quiet hours.

9. **Optional enhancement: current interest materialization**
   - Kept `match_interest_actions` as append-only event log.
   - Added `match_interest_states` for current resolved interest state.

10. **Localization + RTL readiness for Persian-first product**
    - Added `locales`, `i18n_texts`, text-key-based goal fields, and template-based notifications.
    - Replaced direct goal names/descriptions and notification strings with text keys.

---

## Full list of new/changed tables and columns (vs previous baseline)

### New tables
- `locales`
- `i18n_texts`
- `revealable_profile_data`
- `match_interest_states`
- `notification_templates`
- `notification_preferences`

### Changed tables (key column-level changes)

#### `users`
- Added: `preferred_locale`, `ui_direction`
- Added FK: `fk_users_preferred_locale -> locales(code)`

#### `profiles`
- Replaced: `birth_date` -> `birth_year`
- Replaced location model:
  - removed: `city_code`
  - added: `country_code`, `region_code`, `location_cell_l5`, `location_cell_l4`
- Updated related indexes to support coarse geocell matching.

#### `goals`
- Replaced direct copy fields:
  - removed: `name`, `description`
  - added: `title_key`, `description_key`

#### `goal_preference_definitions`
- Added: `label_key`, `helper_text_key`

#### `goal_attribute_definitions`
- Added: `label_key`, `helper_text_key`

#### `match_interest_actions`
- Added: `metadata_json`

#### `match_cards`
- Replaced display copy fields:
  - `age_range_label` -> `age_range_label_key`
  - `emotional_summary` -> `emotional_summary_key`
  - `schedule_overlap_summary` -> `schedule_overlap_key`
- Replaced direct distance number:
  - `approx_distance_km` -> `approx_distance_bucket`

#### `messages`
- Added: `metadata_json`, `moderation_state`

#### `reports`
- Removed: `resolved_by_admin_id`
- Added: `resolution_note`

#### `notifications`
- Reworked from raw title/body to template-based:
  - removed: `type`, `title`, `body`
  - added: `template_id` FK to `notification_templates`, `payload_json`

#### `no_match_states`
- Added: `goal_scope_key` (stored, app-maintained), `is_active`
- Added unique active-scope constraint: `uq_no_match_active_scope`

#### `closure_feedback`
- Added unique constraint: `uq_closure_feedback_match_user`

---

## Table-by-table overview

### `locales`
Defines supported locales and their default direction (`rtl`/`ltr`) for rendering strategy.

### `i18n_texts`
Central translation dictionary keyed by `(namespace, text_key, locale_code)`.
All user-facing copy should resolve through this table (or cache thereof), not hardcoded strings.

### `users`
Authentication + account lifecycle.
Includes `preferred_locale` and `ui_direction` so UI defaults to Persian RTL but can support future bilingual operation.

### `profiles`
Private baseline used for compatibility matching.
Stores `birth_year` (not full birth date), compatibility dimensions, and coarse location tokens.

### `profile_boundaries`
Structured boundaries used for hard filtering and preference weighting.

### `revealable_profile_data`
Sensitive reveal-only data store (first name/photo/contact/deep profile).
Separated from `profiles` to avoid accidental exposure in matching surfaces.

### `availability_slots`
Weekly schedule slots for overlap scoring in matching.

### `goals`
Goal catalog with text keys (`title_key`, `description_key`) for localization-safe rendering.

### `user_goals`
User-selected goals with priority and lifecycle status.

### `goal_preference_definitions`
Data-driven schema for goal-specific input fields.
Includes translation keys for field labels/help text.

### `user_goal_preferences`
Typed storage for user values matching preference definitions.

### `goal_attribute_definitions`
Goal-specific attributes with sensitivity/reveal controls and translation keys.

### `user_goal_attributes`
Typed storage for user goal attributes.

### `match_candidate_queue`
Cron-friendly queue for candidate generation, hard filtering, and scoring.
Allows incremental processing on shared hosting.

### `matches`
Canonical relationship row for user pair + goal.
Stores directional scores, mutual score, explainability payload, and lifecycle state.

### `match_interest_states`
Materialized current interest state for fast read paths.

### `match_interest_actions`
Append-only event log for interest/pass/undo actions.

### `match_cards`
Anonymous viewer-specific intro card payload with translatable text keys and non-identifying summaries.

### `chats`
One chat per match (when mutual interest advances).

### `messages`
Chat messages for polling-based delivery.
Deliberately omits pressure indicators (`seen`, `typing`, `online`, `last_seen`).
Includes lightweight moderation metadata.

### `reveal_requests`
Request lifecycle for stage-based reveal actions.

### `reveal_consents`
Per-user consent records linked to each reveal request.
Two-sided consent enforcement anchor.

### `reports`
Safety reporting model for users.
Uses state + resolution note; moderator identity deferred for MVP consistency.

### `blocks`
Hard pair-level exclusion table.

### `notification_templates`
Translatable notification message templates via `title_text_key` + `body_text_key`.

### `notification_preferences`
Per-user notification controls by category and quiet hours.

### `notifications`
Notification queue referencing templates and interpolation payload.
No hardcoded body/title strings required.

### `no_match_states`
Persistent empty-state handling.
Uniqueness strategy ensures current active state is unambiguous.

### `closure_feedback`
Post-chat or post-match anonymous closure reasons.
Uniqueness prevents duplicate submissions by the same user for the same match.

---

## Required design decisions and rationale

### 1) How localization + RTL support is represented in the schema

- `locales` stores available locales and their rendering direction (`rtl`/`ltr`).
- `users.preferred_locale` + `users.ui_direction` personalize rendering defaults.
- UI copy is key-based (`title_key`, `description_key`, `label_key`, `helper_text_key`, `title_text_key`, `body_text_key`) rather than hardcoded values.
- `i18n_texts` stores localized copy values by namespace/key/locale.
- `notifications` references `notification_templates` and resolves locale text at render/send time using `payload_json` interpolation values.
- This design allows Persian RTL default with future English LTR without schema migration.

### 2) How `revealable_profile_data` is protected and intended to be used

Protection model:
- Sensitive fields are physically separated from baseline `profiles` table.
- Table contains only reveal-intended fields and per-field minimum reveal stages.
- It is linked to users via one-to-one unique key (`uq_revealable_profile_user`).

Usage flow:
1. User updates revealable details into `revealable_profile_data`.
2. Counterpart initiates reveal via `reveal_requests`.
3. Both users decide through `reveal_consents`.
4. Application unlocks field-level data only when consent + stage policy passes.
5. Until then, `match_cards` and baseline matching surfaces remain anonymous.

### 3) How `location_cell_l5` / `location_cell_l4` support privacy-safe distance matching

- `location_cell_l5` = tighter coarse cell for nearby matching candidates.
- `location_cell_l4` = broader fallback cell when l5 has low pool / cold start.
- Matching steps can be staged:
  1. Prefer same country/region + same `l5` for strongest proximity.
  2. Expand to same country/region + same `l4` when needed.
  3. Respect per-user `distance_radius_km` as policy gate in scoring/filtering.
- No exact latitude/longitude is stored, minimizing re-identification risk while retaining usable proximity filtering.

### 4) How event-log + materialized interest state works

Two-table model:
- `match_interest_actions` is append-only audit/event stream (`interested`, `pass`, `undo`) with optional metadata.
- `match_interest_states` stores current resolved interest state per `(match_id, user_id)`.

Operational behavior:
1. On every action insert, app updates corresponding row in `match_interest_states`.
2. UI reads current state from `match_interest_states` (fast).
3. Analytics/audit/replay use `match_interest_actions` (historical truth).
4. Mutual-interest detection reads two state rows and updates `matches.status` accordingly.

### 5) Why JSON fields are used

JSON is used for bounded, evolving payloads where relational rigidity would slow product iteration:
- `goal_preference_definitions.allowed_values_json`
- `user_goal_preferences.value_json`
- `user_goal_attributes.value_json`
- `match_candidate_queue.score_breakdown_json`
- `matches.explanation_json`
- `match_interest_actions.metadata_json`
- `match_cards.match_reasons_json`
- `match_cards.communication_boundaries_json`
- `messages.metadata_json`
- `notifications.payload_json`
- `no_match_states.context_json`
- `revealable_profile_data.contact_payload_json`
- `revealable_profile_data.deep_profile_payload_json`

Principle:
- Core identity, ownership, and flow constraints remain relational.
- JSON is reserved for explainability, metadata, and dynamic per-goal structures.

### 6) How privacy is enforced at schema level

- No public browsing table or public profile feed exists in schema.
- Sensitive reveal fields are isolated in `revealable_profile_data`.
- Discovery output is anonymous via `match_cards`.
- Approximate location is coarse (`region + location cells`), no exact lat/lng columns.
- Two-sided reveal enforced by `reveal_requests` + `reveal_consents`.
- Low-pressure chat posture preserved by omission of presence indicators.
- Block/report safety rails modeled directly.

### 7) How match generation is optimized for shared hosting

- Queue-first matching pipeline in `match_candidate_queue` supports cron batch processing.
- Composite indexes optimize common scans (`status`, user, goal, score, expiry).
- Materialized `match_cards` reduce expensive per-request recomputation.
- Polling-friendly message index (`chat_id, created_at, id`) supports AJAX incremental fetch.
- No external infrastructure requirements (no sockets/Redis/worker fleet).

### 8) How cold-start / no-match handling is implemented

- `no_match_states` persists user empty-state lifecycle instead of ephemeral “no result” responses.
- `state` and `context_json` support supportive UX and actionable suggestions.
- `next_recheck_at` enables periodic cron reevaluation.
- `notify_on_strong_match` enables delayed opt-in notifications.
- Uniqueness on active scope prevents conflicting current state records.

### 9) How staged reveal + mutual consent are enforced

- Reveal intent is recorded in `reveal_requests` with `reveal_type` and `stage_required`.
- Consent decisions are recorded per participant in `reveal_consents`.
- Unlock policy requires bilateral acceptance for the same request.
- Actual reveal content lives in separate `revealable_profile_data`, reducing accidental leaks.

---

## Indexing and constraints highlights

- Match queue:
  - `idx_match_queue_status_user`
  - `idx_match_queue_score`
  - `idx_match_queue_expiry`
- Match feed/cards:
  - `idx_matches_user_a_status`
  - `idx_matches_user_b_status`
  - `idx_match_cards_viewer_created`
- Chat polling:
  - `idx_messages_chat_created`
- Empty-state lifecycle:
  - `uq_no_match_active_scope`
  - `idx_no_match_recheck`
- Closure feedback anti-duplication:
  - `uq_closure_feedback_match_user`
- Reveal safety:
  - `uq_reveal_consents_request_user`
- Notification retrieval:
  - `idx_notifications_user_read_created`

---

## Shared-hosting operational notes

- Run cron frequently for:
  - queue scoring,
  - no-match rechecks,
  - delayed notification dispatch.
- Keep payload JSON compact and avoid unbounded blobs.
- Encrypt highly sensitive reveal payloads at application layer before DB storage.
- Serve photo/contact reveals only after policy check against consent records.
