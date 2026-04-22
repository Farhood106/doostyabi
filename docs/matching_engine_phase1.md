# Compatibility Engine (Phase 1)

## Added files
- `src/Repositories/Matching/CandidateRepository.php`
- `src/Services/Matching/HardFilterService.php`
- `src/Services/Matching/CompatibilityScoringService.php`
- `src/Services/Matching/ExplanationBuilderService.php`
- `src/Services/Matching/CandidateQueueService.php`
- `src/Services/Matching/NoMatchStateService.php`
- `src/Services/Matching/CandidateGenerationService.php`
- `bin/cron_generate_candidates.php`

## Candidate selection strategy (staged buckets)
For each source user, candidate IDs are discovered in stages:

1. **Strict nearby**
   - same `country_code`
   - same `region_code`
   - same `location_cell_l5`
   - only runs when source has all three fields populated

2. **Relaxed nearby** (if still below target limit)
   - same `country_code`
   - same `region_code`
   - same `location_cell_l4`
   - only runs when source has country/region/L4 populated

3. **Broader fallback** (if still below target limit)
   - same `country_code`
   - only runs when source has country populated

This avoids cross-comparing `location_cell_l4` with candidate `location_cell_l5` and keeps retrieval deterministic and shared-hosting friendly.

## Hard filters
Minimum enforced exclusions:
1. same user
2. blocked pair
3. inactive users
4. no active goal overlap
5. age preference mismatch (both directions)
6. **country mismatch** (mandatory geography gate)
7. boundary conflicts (symmetric required/avoid checks)
8. strong lifestyle contradiction (smoking+drinking conflict together)

Output fields:
- `eligible_for_display`
- `rejection_reason_codes`

### Geography rule rationale
Country remains a hard filter for safety/privacy and practical distance constraints in MVP.
Region/cell are ranking/selection signals to avoid over-strict cold-start rejection.

## Scoring model
Weighted score out of 100:
- goal alignment: 20%
- core dimensions: 35%
- lifestyle similarity: 10%
- distance fit: 10%
- schedule overlap: 15%
- mutual preference fit: 10%

Outputs:
- `compatibility_score`
- `score_breakdown`
- `top_match_reasons`
- `caution_points`

### Symmetric schedule overlap formula
Schedule overlap uses a symmetric ratio:

`overlap_minutes / union_minutes`

where:
- `overlap_minutes` = summed intersection minutes on matching weekdays
- `union_minutes` = `total_source_minutes + total_candidate_minutes - overlap_minutes`

This is balanced for both users and avoids one-sided normalization.
Before calculating overlap, each side's slots are normalized per weekday
(invalid slots removed, intervals merged) so overlap/union is not inflated by
duplicated or overlapping input rows.

## Queue updates
- Writes to `match_candidate_queue` through upsert on `(user_id, candidate_user_id, goal_id)`.
- **Queue is written per overlapping goal** (not just first overlap).
- Rejected candidates store `rejection_reason_code` and short expiry (`+2 days`).
- Scored candidates store explanation payload JSON and longer expiry (`+7 days`).
- Low scores are marked rejected with `low_compatibility_score`.

## No-match lifecycle
After per-user processing:
- if strong candidates (`score >= 70`) exist:
  - deactivate active no-match state for the scope (user is no longer in no-match)
- otherwise:
  - deactivate previous active no-match state
  - insert new active state:
    - `profile_improvement_suggested` when profile quality is weak
    - `expand_preferences_suggested` otherwise

This keeps active no-match rows semantically correct.

## Cron usage
Example crontab every 10 minutes:

```cron
*/10 * * * * /usr/bin/php /home/USER/app/bin/cron_generate_candidates.php 50 0 200 >> /home/USER/logs/candidate_cron.log 2>&1
```

Arguments:
1. users batch limit
2. users batch offset
3. candidate limit per user

## Verification fixture (lightweight)
Run this executable fixture without a full test framework:

```bash
php bin/verify_matching_phase1.php
```

It uses an in-memory SQLite database to verify:
- strict/relaxed/fallback candidate selection tiers
- blocked pair and country mismatch hard rejections
- symmetric schedule-overlap scoring
- queue rows for all overlapping goals
- no-match state deactivation when strong candidates exist
- profile improvement state when profile is weak and no strong candidates exist

## Match card build and delivery (v1)
Cards are built from eligible queue rows in `match_candidate_queue` where:
- `hard_filter_passed = 1`
- `compatibility_score` is present
- `status IN ('scored', 'presented')`
- row is not expired

Builder output is materialized into `match_cards` and linked through `matches`:
- `age_range_label_key` from candidate age bucket (e.g., `age_25_29`)
- `approx_distance_bucket` from coarse location (`same_area`, `nearby_region`, `same_country`)
- `compatibility_score` (numeric score only; no identity fields)
- `emotional_summary_key` from score/profile-safe rule engine
- `match_reasons_json` filtered to translatable explanation keys
- `communication_boundaries_json` filtered to safe abstract boundaries
- `schedule_overlap_key` (`schedule_overlap_high|medium|low`)
- `card_version` for refresh/versioning

### Privacy-safe transformations
The card builder intentionally excludes:
- names, photos, phone/email/handles, exact location and direct identifiers
- revealable profile data and contact fields
- non-key free-form explanation text

Only safe summary artifacts are used (coarse profile signals, boundary tags, score keys).

### Delivery ordering
Card delivery reads `match_cards` joined to active `matches` states and returns:
1) highest `compatibility_score` first
2) newest `updated_at` as tie-breaker
3) stable `id` order as final tie-breaker

### Refresh flow
`bin/cron_build_match_cards.php` refreshes cards by upserting from queue state and sets
queue rows to `presented` after successful card upsert. Re-running refresh keeps cards in
sync with the latest scored queue data and preserves future compatibility for one-sided/mutual interest flow.

## Mutual-interest flow (v1)
Viewer actions on anonymous cards:
- `interested`
- `pass`
- `undo`

Each action:
1. appends an immutable row in `match_interest_actions`
2. upserts current state in `match_interest_states` for `(match_id, user_id)`

### Lifecycle choice
- Initial match status: `suggested`
- When both sides become `interested`:
  - set `matches.status = mutual`
  - ensure a `chats` row exists for the match
  - set `matches.status = chat_open`

This keeps a clear transition point while opening chat immediately when mutual is confirmed.

### Pass and undo behavior
- `pass` updates the current state to `passed` (history retained in actions log)
- Cards with viewer current state `passed` are excluded from normal delivery
- `undo` sets current state back to `none`, allowing the card to be delivered again

## Mutual-interest verification fixture
Run:

```bash
php bin/verify_mutual_interest_flow.php
```

It verifies:
- interested action persistence
- pass action persistence
- undo action persistence and state reset
- mutual detection across both users
- chat creation on mutual
- passed-card exclusion from active delivery

## Chat foundation and entry flow (v1)
### Access rules
- Chat entry route: `GET /chat?chat_id=<id>` (or `match_id` resolution fallback).
- Access is allowed only when authenticated user is one of the match participants.
- Chat must be `open` and match must be in `mutual` or `chat_open`.
- Non-participants and invalid chat/match pairs are blocked and redirected.

### Sending messages
- `POST /chat/send` inserts a `messages` row with `message_type='text'`.
- Allowed only for chat participants and only while chat status is `open`.
- Message body is validated (non-empty, max length).
- Hidden/deleted messages are excluded from reader queries.

### Polling design
- `GET /chat/poll?chat_id=<id>&since_id=<message_id>`
- Returns only messages for that specific accessible chat and only `id > since_id`.
- Lightweight AJAX polling (`setInterval`) keeps shared-hosting compatibility.
- When session is missing/expired, polling returns JSON `401` (`unauthorized`) instead of redirect HTML.

### Intentional omissions (privacy/safety MVP scope)
- no seen/read receipts
- no typing indicator
- no online/last-seen presence

### Chat-open card linkage
- Dashboard cards already at `mutual` / `chat_open` show chat entry action when `chat_id` exists.
- Normal interest controls are suppressed for these cards.

## Chat verification fixture
Run:

```bash
php bin/verify_chat_flow.php
```

It verifies:
- authorized chat access
- unauthorized chat access blocked
- send message success
- send message blocked for non-participants
- polling returns only relevant/new messages

## Progressive reveal flow (v1)
### Request lifecycle
Supported reveal types:
- `first_name`
- `photo`
- `contact_info`
- `deep_profile`

Lifecycle states:
1. `pending` (request created)
2. `accepted` (counterparty accepts)
3. `declined` (counterparty declines)
4. `cancelled` (requester cancels while pending)
5. `expired` (pending request past `expires_at`)

### Two-sided consent model
- Request creation auto-records requester consent = `accepted`.
- Counterparty must explicitly `accept` in `reveal_consents`.
- Data is visible only when:
  - request status is `accepted`
  - requester consent is `accepted`
  - counterparty consent is `accepted`
- Duplicate pending requests (same requester + match + reveal_type) are blocked.
- `respond` / `cancel` normalize expiration first; expired pending requests are not actionable.

### Stage-aware enforcement
- Reveal type is gated by the owner’s stage requirement columns on `revealable_profile_data`.
- Current stage is derived from match status (`suggested`/`interested_one_side` => stage_1, `mutual` => stage_2, `chat_open` => stage_3).
- Requests exceeding current stage are blocked server-side.

### Chat integration
- Chat page includes:
  - create reveal request form
  - incoming pending requests with accept/decline
  - outgoing pending requests with cancel
  - unlocked reveal values section (only truly unlocked items)

## Reveal verification fixture
Run:

```bash
php bin/verify_reveal_flow.php
```

It verifies:
- request creation
- accept flow
- decline flow
- hidden until unlocked
- visible after proper consent
- unauthorized access blocked
