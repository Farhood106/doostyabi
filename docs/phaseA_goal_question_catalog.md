# Phase A Plan: Goal-Specific Question Catalog + Preference Definitions

## Scope and constraints
This document defines onboarding question design only.

- ✅ In scope: question catalog, pref_keys, answer types, hard/soft priority, match-card eligibility, UX order, and seed strategy.
- ❌ Out of scope: scoring implementation details and match-engine code changes.
- ✅ Schema reuse: use `goal_preference_definitions` and `user_goal_preferences`.
- ⚠️ Schema change only if needed: **not required** for Phase A.

## Design principles (low-friction + high-signal)
1. Ask only purpose-driven questions.
2. Use 4–6 goal-specific questions after goal selection.
3. Ask hard-filter questions first (safety, boundaries, role compatibility).
4. Keep personality-style questions minimal and late.
5. Prefer single_select / yes_no for speed; avoid open text early.

## Reuse and de-duplication policy (important)
Shared baseline keys must not be repeatedly asked in each goal flow.

- Ask global baseline questions **once**.
- Goal-specific flows can **reference existing answers** for hard-filter/scoring.
- Ask a goal-specific variant only when meaning is materially different.

Default shared keys to ask once:
- `must.boundary_respect`
- `privacy.discretion_level`
- `pace.response_cadence`
- `involvement.time_intensity`

## Shared onboarding questions (ask once for everyone)
These questions are shown before any goal-specific micro-flow.

| Question title | Helper text | pref_key | type | Options (localized-ready values) | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Respect for boundaries | We only suggest matches with mutual boundary respect. | `must.boundary_respect` | yes_no | `required`, `not_sure` | hard_filter | yes | boundary_note, caution |
| Privacy preference | How private should this introduction stay at first? | `privacy.discretion_level` | single_select | `low`, `medium`, `high`, `strict_high` | hard_filter (sensitive goals), strong_score otherwise | yes | expectation_alignment, caution |
| Conversation pace | What reply pace feels comfortable for you? | `pace.response_cadence` | single_select | `same_day`, `1_2_days`, `few_days`, `flexible` | strong_score | yes | expectation_alignment, reason |
| Time availability | How much time can you realistically give weekly? | `involvement.time_intensity` | single_select | `occasional`, `weekly`, `several_times_week` | strong_score | yes | expectation_alignment |

### Sensitive-goal hard-filter override
For **casual/non-committed** and **support-based** matching:
- `not_sure` on `must.consent_style`, `must.boundary_respect`, or strict privacy requirement must **not pass**.
- Treat `not_sure` as `incomplete` for onboarding and `hard_reject` for sensitive matching eligibility.
- User must provide explicit compatible values before entering sensitive candidate pools.

---

## Goal cluster catalog

## 1) Friendship / conversation

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Conversation energy | Pick the conversation style you enjoy. | `seek.social_energy` | single_select | `quiet_low_pressure`, `balanced`, `high_energy` | soft_score | yes | reason |
| What you offer in conversation | How do you usually show up for a friend? | `offer.social_energy` | single_select | `calm_listener`, `balanced`, `high_energy_initiator` | soft_score | yes | reason |
| Topic comfort | Which topics are okay early on? | `accept.topic_scope` | multi_select | `daily_life`, `hobbies`, `work_study`, `values_light`, `deeper_topics_later` | soft_score | no | none |
| Friendship intent horizon | Are you exploring or seeking ongoing friendship? | `duration.friendship_intent` | single_select | `explore`, `ongoing` | strong_score | yes | expectation_alignment |

## 2) Emotional connection

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Desired emotional climate | What emotional tone are you seeking? | `seek.emotional_climate` | single_select | `calm`, `warm`, `deep` | strong_score | yes | reason |
| Emotional availability you offer | What can you realistically offer? | `offer.emotional_availability` | single_select | `light_checkins`, `steady_support`, `high_presence` | strong_score | yes | reason |
| Communication comfort | Which communication formats feel okay? | `accept.communication_style` | multi_select | `text_short`, `voice`, `scheduled_calls`, `infrequent_ok` | soft_score | no | none |
| Relationship pace | How quickly should connection evolve? | `pace.connection_speed` | single_select | `slow`, `moderate`, `fast` | strong_score | yes | expectation_alignment |
| Connection horizon | What timeline are you open to? | `duration.relationship_horizon` | single_select | `explore_1_3_months`, `mid_term`, `long_term_open` | strong_score | yes | expectation_alignment |

## 3) Long-term relationship

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Long-term intent clarity | How clearly long-term are your intentions? | `must.commitment_intent` | single_select | `explicit_long_term`, `long_term_preferred`, `open_but_unsure` | hard_filter for strict seekers; strong_score otherwise | yes | reason, caution |
| What you seek in stability | What stability level do you want? | `seek.stability_level` | single_select | `steady`, `highly_structured`, `flexible_but_reliable` | strong_score | yes | reason |
| What you offer in consistency | What consistency can you offer? | `offer.consistency_level` | single_select | `weekly_reliable`, `high_reliability`, `moderate_reliability` | strong_score | yes | reason |
| Pace toward commitment | Preferred pace for serious progression. | `pace.commitment_pace` | single_select | `slow_intentional`, `moderate`, `fast_if_aligned` | strong_score | yes | expectation_alignment |
| Conflict style compatibility | Which style can you accept? | `accept.conflict_style` | multi_select | `calm_discussion`, `direct_but_respectful`, `needs_time_then_talk` | soft_score | no | none |

## 4) Travel / activity companion

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Activity type sought | What kind of outing are you looking for? | `seek.activity_type` | multi_select | `city_walk`, `museum`, `food_explore`, `hiking`, `road_trip` | strong_score | yes | reason, opener_basis |
| Planning style offered | How do you plan trips/activities? | `offer.planning_style` | single_select | `structured`, `hybrid`, `spontaneous` | strong_score | yes | reason |
| Budget comfort | Which budget range is comfortable? | `accept.budget_band` | single_select | `low`, `medium`, `high`, `mixed_by_plan` | strong_score | yes | expectation_alignment |
| Public-first meeting required | Safety first for initial activity. | `must.public_meet_first` | yes_no | `required`, `not_required` | hard_filter | yes | boundary_note |
| Decision speed | How fast do you like deciding plans? | `pace.trip_decision_speed` | single_select | `same_day`, `few_days`, `week_plus` | strong_score | yes | expectation_alignment |
| Trip window | What duration do you prefer? | `duration.trip_window` | single_select | `half_day`, `day_trip`, `weekend`, `multi_day` | strong_score | yes | expectation_alignment |

## 5) Sports / wellness

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Activity intensity sought | What intensity are you looking for? | `seek.activity_intensity` | single_select | `light`, `moderate`, `high` | strong_score | yes | reason |
| Intensity you can offer | What intensity can you sustain? | `offer.activity_intensity` | single_select | `light`, `moderate`, `high` | strong_score | yes | reason |
| Session cadence comfort | How often is realistic for you? | `pace.session_cadence` | single_select | `weekly`, `2_3_week`, `daily` | strong_score | yes | expectation_alignment |
| Safety boundary required | Respecting physical limits is mandatory. | `must.safety_boundary_respect` | yes_no | `required`, `not_sure` | hard_filter | yes | boundary_note, caution |
| Goal horizon | How long do you want this routine? | `duration.wellness_horizon` | single_select | `1_month`, `3_months`, `ongoing` | strong_score | yes | expectation_alignment |

## 6) Collaboration / project

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Role you need | Which role should your match fill? | `seek.partner_role` | multi_select | `planner`, `builder`, `designer`, `operator`, `researcher` | hard_filter (strict role need) or strong_score | yes | reason |
| Role you offer | What role can you contribute? | `offer.role` | multi_select | `planner`, `builder`, `designer`, `operator`, `researcher` | strong_score | yes | reason |
| Collaboration format | Which work style can you accept? | `accept.collab_format` | multi_select | `async`, `weekly_sync`, `daily_sync` | strong_score | yes | expectation_alignment |
| Commitment reliability required | Collaboration fails without reliability. | `must.commitment_reliability` | yes_no | `required`, `not_sure` | hard_filter | yes | boundary_note, caution |
| Decision cadence | How quickly should project decisions happen? | `pace.decision_cadence` | single_select | `slow_deliberate`, `regular`, `fast_iterative` | strong_score | yes | expectation_alignment |
| Project horizon | Expected time span for collaboration. | `duration.project_horizon` | single_select | `2_weeks`, `1_3_months`, `3_plus_months` | strong_score | yes | expectation_alignment |

## 7) Casual / non-committed adult connection (respectful)

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Connection expectation | What kind of low-commitment dynamic are you seeking? | `seek.connection_expectation` | single_select | `light_chat`, `companionship`, `low_commitment_romantic` | strong_score | yes | reason |
| Emotional involvement comfort | What level is acceptable for you? | `accept.emotional_involvement_level` | single_select | `low`, `moderate` | strong_score | yes | expectation_alignment |
| Privacy/discretion level | How private must this remain? | `privacy.discretion_level` | single_select | `high_preferred`, `strict_high_required` | hard_filter | yes | boundary_note, caution |
| Pace comfort | What pace feels right for you? | `pace.intimacy_pace` | single_select | `very_slow`, `slow`, `mutually_set` | strong_score | yes | expectation_alignment |
| Ongoing consent model | Consent must stay affirmative and ongoing. | `must.consent_style` | single_select | `affirmative_and_ongoing`, `not_sure` | hard_filter | yes | boundary_note, caution |
| Boundary respect required | Clear boundaries are mandatory. | `must.boundary_respect` | yes_no | `required`, `not_sure` | hard_filter | yes | boundary_note, caution |
| Appearance preference (optional/general) | Optional: broad preference, respectful wording only. | `accept.appearance_preference_optional_general` | optional_text | `free_text_optional` | soft_score | no | none |
| Connection window | How long are you open to this dynamic? | `duration.connection_window` | single_select | `few_weeks`, `1_3_months`, `open_ended_light` | strong_score | yes | expectation_alignment |

## 8) Support-based relationship

| Question title | Helper text | pref_key | type | Options | priority | show_in_match_card | card_mapping |
|---|---|---|---|---|---|---|---|
| Support role needed | Which role are you currently seeking? | `seek.support_role` | single_select | `seek_support`, `flexible` | hard_filter/strong_score | yes | reason |
| Support type needed | What type of support are you seeking? | `seek.support_type` | multi_select | `mentorship`, `practical_help`, `lifestyle_support` | hard_filter/strong_score | yes | reason |
| Support role offered | Which role can you offer? | `offer.support_role` | single_select | `offer_support`, `flexible` | hard_filter/strong_score | yes | reason |
| Support type offered | What support can you provide? | `offer.support_type` | multi_select | `mentorship`, `practical_help`, `lifestyle_support` | strong_score | yes | reason |
| Expectation clarity comfort | How explicit should expectations be? | `accept.support_expectation_clarity` | single_select | `explicit_required`, `clear_preferred` | strong_score | yes | expectation_alignment |
| Transparency required | Expectations must remain explicit. | `must.transparency_level` | yes_no | `required`, `not_sure` | hard_filter | yes | boundary_note, caution |
| Discretion level | How private must this arrangement remain? | `privacy.discretion_level` | single_select | `high`, `strict_high` | hard_filter | yes | boundary_note, caution |
| Setup speed | How quickly should arrangement terms be defined? | `pace.arrangement_setup_speed` | single_select | `slow`, `moderate` | strong_score | yes | expectation_alignment |
| Arrangement horizon | How long should this arrangement run? | `duration.arrangement_horizon` | single_select | `1_month`, `3_months`, `6_months_plus` | strong_score | yes | expectation_alignment |

### Support polarity rule (mandatory)
- Two users with `seek.support_role=seek_support` and no compatible `offer.support_role/type` must not match.
- Two users with provider-only profiles should not match under support-based goal unless they also share compatibility in another active goal cluster.

---

## Questions intentionally removed (low-value early onboarding)
Avoid showing early unless a specific goal proves they are needed:

- Generic “introvert/extrovert” personality label without goal context.
- Broad “favorite movie/music” style questions.
- Deep biography free text in first onboarding pass.
- Detailed ideology/religion/politics early prompts.
- Any explicit wording in sensitive goals.

These can appear later as optional profile enrichment, not initial matching input.

## Primary-goal MVP onboarding rule
For initial implementation:

1. Ask shared baseline once.
2. Ask detailed questions only for the **primary selected goal**.
3. Save secondary goals immediately, but defer their detailed question flows.
4. Later surface “Improve match quality” prompts for deferred secondary-goal questions.

This keeps onboarding short while preserving multi-goal intent.

## Recommended question order (smart + low-friction)

### Step 1: Goal selection
- Multi-select goals.
- Ask user to choose **primary goal** for immediate matching.

### Step 2: Shared safety baseline (4 quick questions)
1. `must.boundary_respect`
2. `privacy.discretion_level`
3. `pace.response_cadence`
4. `involvement.time_intensity`

### Step 3: Primary-goal micro-flow (4–6 questions)
- Start with hard filters.
- Then strong score questions.
- End with one soft score question max.

### Step 4: Deferred enrichment
- Show “Improve match quality” cards after onboarding completion.
- Ask secondary-goal and soft-score-only questions later, not as blockers.

## Hard filter policy by question type

### Typical hard-filter keys
- `must.*` keys by default.
- `privacy.discretion_level` for casual/support sensitive goals.
- strict role-compatibility keys (`seek.*` vs `offer.*`) where role mismatch invalidates pairing.

### Sensitive-goal hard filter strictness
For casual/support clusters:
- `not_sure` on consent, boundaries, or strict privacy behaves as `incomplete` and blocks sensitive matching.
- Explicit compatible values are required to enter sensitive matching.

### Typical strong-score keys
- `seek.*`, `offer.*`, `pace.*`, `duration.*`.

### Typical soft-score keys
- `accept.*` where flexibility exists.
- style preferences not safety-critical.

## Match-card explanation mapping guide
For fields with `show_in_match_card=yes`, map outputs as follows:

- `reason`: purpose-fit or reciprocity explanation.
- `boundary_note`: mutual safety/consent/boundary reassurance.
- `expectation_alignment`: pace/duration/privacy alignment sentence.
- `caution`: gentle mismatch note when non-blocking.
- `opener_basis`: starter prompt topic seed (e.g., activity style).

Never expose exact sensitive values or identity details on cards.

## Seed-ready admin strategy

## A) `goal_preference_definitions` seed fields
For each row, prepare:
- `goal_id`
- `pref_key`
- `question_i18n_key` (new i18n key path)
- `helper_i18n_key` (new i18n key path)
- `answer_type`
- `allowed_values_json` (value keys only)
- `is_required` (`1` for hard filters and critical strong-score)
- `weight` (coarse defaults for future scoring)
- `is_active`

> No schema change required if `question_i18n_key` / `helper_i18n_key` already exist in definition records. If missing, store these key names in `pref_key`-derived convention until schema-level metadata is introduced.

## B) i18n seed convention (`database/i18n_seed_main_flow.sql`)
Insert localized strings for:
- `goal_q.<goal_slug>.<pref_key>.title`
- `goal_q.<goal_slug>.<pref_key>.helper`
- `goal_q.option.<pref_key>.<option_value>`

This keeps option labels centralized and reusable in onboarding + review screens.

## C) Admin review sheet output
Generate an internal CSV/Markdown export with:
- goal cluster
- pref_key
- answer_type
- options
- priority
- show_in_match_card
- card_mapping
- i18n title/helper key

This enables product/legal/language sign-off before rollout.

## Final recommendations

## Which questions should be asked for everyone
- `must.boundary_respect`
- `privacy.discretion_level`
- `pace.response_cadence`
- `involvement.time_intensity`

## Which questions should be asked only after selecting a goal
- Primary goal: all required `seek.*`, `offer.*`, goal-specific `must.*`, and critical `pace.*`/`duration.*`.
- Secondary goals: defer until post-onboarding improvement prompts.

## Which questions should never be shown early
- Optional free-text (`optional_text`) prompts.
- High-friction personal narrative requests.
- Non-goal-driven personality filler.
- Sensitive detail prompts beyond safety and boundary necessities.

## UX recommendations
1. Keep first pass under 2 minutes.
2. Use progressive disclosure: only ask questions needed for selected goals.
3. Show “Why this question?” helper for sensitive prompts.
4. Present 2–5 options max per single-select for fast decision.
5. Use plain, respectful, non-explicit language in sensitive goals.
6. Add “skip for now” only for soft-score questions.
7. After onboarding, use lightweight improvement nudges instead of long forms.
