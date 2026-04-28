# Matching Engine (Phase 2): Purpose + Need/Offer Compatibility

## Objective
Phase 2 shifts matching from generic similarity toward **purpose fulfillment with safety guardrails**.
The ranking logic becomes goal-aware and supports both:
- **similarity matching** (shared style/values), and
- **complementary matching** (one side needs what the other can offer).

This phase is designed to work on top of the existing schema (`goals`, `user_goals`,
`goal_preference_definitions`, `user_goal_preferences`) with minimal migration pressure.

## Decision hierarchy
Candidate evaluation should follow this strict order:

1. **Safety and legal fit** (hard filters)
2. **Purpose fit** (same/compatible active goal)
3. **Need/offer reciprocity** (A seeks B offers and B seeks A offers)
4. **Consent/boundary/privacy alignment**
5. **Pace/duration/involvement alignment**
6. **Feasibility** (distance, availability)
7. **Optional personality fit**

If a hard filter fails, scoring should not proceed.

## Goal clusters and match mode defaults
Use cluster-specific strategy profiles instead of one global scorer.

1. Friendship / conversation
2. Emotional connection
3. Long-term relationship
4. Travel / activity companion
5. Sports / wellness companion
6. Collaboration / project
7. Casual, non-committed adult connection (respectful + consent-based)
8. Support-based relationship (transparent expectations + consent-based)

Recommended default mode per cluster:
- Friendship: similarity-first
- Emotional/Long-term: similarity + expectation alignment
- Travel/Sports: feasibility + style alignment
- Collaboration: complementarity (role fit)
- Casual: consent/privacy/boundary-first
- Support-based: reciprocal need/offer-first

## Preference key model (Phase 2 naming convention)
For each active goal, preference keys should follow grouped namespaces:

- `seek.*` — what the user wants from a match
- `offer.*` — what the user can provide
- `accept.*` — what the user can tolerate/flex on
- `must.*` — non-negotiables (hard or near-hard)
- `privacy.*` — discretion and sharing comfort
- `pace.*` — speed/cadence expectations
- `duration.*` — expected horizon
- `involvement.*` — emotional/practical intensity

This keeps logic declarative through existing `goal_preference_definitions.pref_key`.

## Goal-cluster key examples (concrete values)
The examples below are illustrative defaults for catalog design.

### Emotional connection
- `seek.emotional_climate`: `calm|warm|deep`
- `offer.emotional_availability`: `light_checkins|steady_support|high_presence`
- `accept.communication_style`: `short_messages_ok|voice_ok|infrequent_ok`
- `must.boundary_respect`: `required`
- `privacy.discretion_level`: `low|medium|high`
- `pace.connection_speed`: `slow|moderate|fast`
- `duration.relationship_horizon`: `explore_1_3_months|mid_term|long_term_open`
- `involvement.emotional_intensity`: `low|moderate|high`

### Travel / activity companion
- `seek.activity_type`: `city_walk|museum|hiking|road_trip`
- `offer.planning_style`: `structured|hybrid|spontaneous`
- `accept.budget_band`: `low|medium|high`
- `must.public_meet_first`: `required`
- `privacy.location_sharing_comfort`: `coarse_only|city_level|region_level`
- `pace.trip_decision_speed`: `same_day|few_days|week_plus`
- `duration.trip_window`: `half_day|day_trip|weekend|multi_day`
- `involvement.coordination_effort`: `minimal|shared|high`

### Collaboration / project
- `seek.partner_role`: `planner|builder|designer|operator`
- `offer.role`: `planner|builder|designer|operator`
- `accept.collab_format`: `async|weekly_sync|daily_sync`
- `must.commitment_reliability`: `required`
- `privacy.public_visibility`: `private|semi_public|public_ok`
- `pace.decision_cadence`: `slow_deliberate|regular|fast_iterative`
- `duration.project_horizon`: `2_weeks|1_3_months|3_plus_months`
- `involvement.workload_level`: `light|part_time|intense`

### Casual, non-committed adult connection (respectful)
- `seek.connection_type`: `light_chat|companionship|low_commitment_romantic`
- `offer.expectation_clarity`: `explicit_boundaries|clear_intentions|consistent_checkins`
- `accept.emotional_involvement_level`: `low|moderate`
- `must.consent_style`: `affirmative_and_ongoing`
- `must.boundary_respect`: `required`
- `privacy.discretion_level`: `high_preferred|strict_high_required`
- `pace.intimacy_pace`: `very_slow|slow|mutually_set`
- `duration.connection_window`: `few_weeks|1_3_months|open_ended_light`
- `involvement.time_intensity`: `occasional|weekly|flexible`

### Support-based relationship (consent-based, transparent)
- `seek.support_type`: `mentorship|practical_help|lifestyle_support`
- `offer.support_type`: `mentorship|practical_help|lifestyle_support`
- `accept.communication_formality`: `formal|neutral|friendly`
- `must.transparency_level`: `expectations_explicit`
- `must.safety_boundaries`: `required`
- `privacy.discretion_level`: `high|strict_high`
- `pace.arrangement_setup_speed`: `slow|moderate`
- `duration.arrangement_horizon`: `1_month|3_months|6_months_plus`
- `involvement.engagement_level`: `light_structured|moderate_structured|high_structured`

### Friendship / conversation (reference)
- `seek.social_energy`: `introvert_friendly|balanced|extrovert_energy`
- `offer.social_energy`: `introvert_friendly|balanced|extrovert_energy`
- `accept.response_speed`: `same_day|few_days`
- `must.respectful_communication`: `required`
- `privacy.disclosure_speed`: `slow|moderate`
- `pace.conversation_frequency`: `occasional|weekly|frequent`
- `duration.friendship_intent`: `explore|ongoing`
- `involvement.friendship_depth`: `light|medium|deep`

## Respectful language policy (content and UX copy)
This policy is mandatory for onboarding prompts, card explanations, notifications, and starter prompts.

### Applies strongly to
- Casual, non-committed adult connection
- Support-based relationship

### Allowed wording characteristics
- respectful
- consent-based
- privacy-first
- non-vulgar
- non-coercive
- clear expectations

### Forbidden wording patterns
- explicit sexual wording
- transactional/manipulative phrasing
- pressure or urgency framing
- identity exposure requests
- objectifying language

### Practical copy rules
- Use neutral terms such as “comfort”, “boundaries”, “expectations”, “pace”, “discretion”.
- Avoid explicit anatomy or explicit-act language.
- Never imply entitlement (e.g., “you should”, “you owe”).
- Never imply certainty of success; use “may be compatible”, “could be a fit”.
- Promote bilateral consent and reversible decisions (“you can pass/undo anytime”).

## Hard filter policy (expanded)
In addition to current hard filters, Phase 2 should support goal-aware hard rejects:

- legal age or policy violations
- blocked pair
- no compatible goal pair for the evaluated goal
- strict boundary conflict (`must.*` incompatibility)
- privacy floor incompatibility in sensitive clusters
- incompatible role polarity for complement goals (e.g., seek-only vs seek-only)
- explicit forbidden expectation classes per policy profile

All hard filter outcomes should continue producing machine-readable reason codes.

## Scoring components
Normalize each component to `0..100`, then apply cluster-specific weights.

`final_score = Σ(weight_i * component_i) - penalties`

Core components:
1. Goal fit
2. Need/offer reciprocity fit (bidirectional)
3. Consent & boundary fit
4. Privacy/discretion fit
5. Pace/duration fit
6. Availability/distance fit
7. Optional personality/style fit
8. Risk/conflict penalties

### Need/offer reciprocity (directional)
Calculate both directions, then combine:
- `fit_ab`: source `seek.*` vs candidate `offer.*`
- `fit_ba`: candidate `seek.*` vs source `offer.*`
- `need_offer_fit = (fit_ab + fit_ba) / 2`

If a key is marked required in definition metadata and missing on either side,
apply severe penalty or hard reject (policy-controlled per cluster).

## Scoring weight refinement (personality is contextual)
Personality/social similarity is **not globally dominant**. Its weight depends on goal intent.

- Emotional/Long-term: personality can be meaningful but still below safety and boundaries.
- Travel/Collaboration: practicality and role/plan compatibility outrank personality.
- Casual/Support-based: purpose fit + consent + privacy + boundaries + need/offer should outrank personality by a large margin.

For short-term/casual/support profiles, personality similarity can be optional or near-zero,
and should never rescue a boundary/privacy mismatch.

## Suggested weight profiles (initial defaults)
Values are intentionally coarse and should be tunable after telemetry.

### Friendship
- goal 20
- need/offer 10
- consent/boundary 20
- privacy 10
- pace/duration 10
- availability/distance 20
- personality 10

### Emotional connection
- goal 20
- need/offer 20
- consent/boundary 20
- privacy 10
- pace/duration 15
- availability/distance 5
- personality 10

### Long-term relationship
- goal 25
- need/offer 20
- consent/boundary 20
- privacy 10
- pace/duration 15
- availability/distance 5
- personality 5

### Travel/activity
- goal 20
- need/offer 10
- consent/boundary 15
- privacy 5
- pace/duration 10
- availability/distance 30
- personality 10

### Sports/wellness
- goal 20
- need/offer 15
- consent/boundary 15
- privacy 5
- pace/duration 15
- availability/distance 20
- personality 10

### Collaboration/project
- goal 20
- need/offer 30
- consent/boundary 15
- privacy 5
- pace/duration 15
- availability/distance 10
- personality 5

### Casual, non-committed adult
- goal 15
- need/offer 25
- consent/boundary 30
- privacy 20
- pace/duration 10
- availability/distance 5
- personality 0..3 optional

### Support-based relationship
- goal 15
- need/offer 35
- consent/boundary 20
- privacy 15
- pace/duration 10
- availability/distance 5
- personality 0..3 optional

## Concrete matching scenarios (product logic examples)
Use these scenarios as reviewer-friendly references for policy and fixture design.

### 1) Introvert seeking extrovert (friendship)
- What matches:
  - `seek.social_energy=introvert_friendly` (A) with `offer.social_energy=balanced` (B)
  - pacing compatibility (`pace.conversation_frequency=weekly`)
- What does NOT need to be similar:
  - hobbies, message length style, humor type
- Hard filters:
  - respectful communication and no boundary conflicts
- Scoring priorities:
  - social-energy complementarity > personality similarity
- Card should say:
  - “Both of you align on low-pressure conversation rhythm and respectful boundaries.”

### 2) User seeking emotional calmness (emotional connection)
- What matches:
  - `seek.emotional_climate=calm` with `offer.emotional_availability=steady_support`
  - `pace.connection_speed=slow|moderate`
- What does NOT need to be similar:
  - extroversion level, daily schedule exactness
- Hard filters:
  - `must.boundary_respect=required`, incompatible privacy floors
- Scoring priorities:
  - emotional climate + boundaries + pace
- Card should say:
  - “This introduction emphasizes calm communication and consistent emotional presence.”

### 3) Travel companion (travel/activity)
- What matches:
  - compatible `seek.activity_type` and `offer.planning_style`
  - acceptable `accept.budget_band`
- What does NOT need to be similar:
  - personality archetype or long-term relationship goals
- Hard filters:
  - `must.public_meet_first=required`
- Scoring priorities:
  - feasibility (distance/schedule) + planning compatibility
- Card should say:
  - “You align on trip style, planning expectations, and safe first-meet preferences.”

### 4) Support-seeker with support-provider (support-based)
- What matches:
  - `seek.support_type` must map to candidate `offer.support_type`
  - transparency and boundary rules align (`must.transparency_level`, `must.safety_boundaries`)
- What does NOT need to be similar:
  - social personality similarity
- Hard filters:
  - seek/seek or offer/offer polarity mismatch
  - privacy floor mismatch
- Scoring priorities:
  - reciprocal need/offer + transparency + discretion
- Card should say:
  - “Your support expectations and discretion preferences are mutually compatible.”

### 5) Casual/non-committed privacy-first connection
- What matches:
  - expectation clarity + boundary respect + high discretion
  - mutually compatible pace (`pace.intimacy_pace=very_slow|slow|mutually_set`)
- What does NOT need to be similar:
  - personality style, extroversion, hobbies
- Hard filters:
  - consent model mismatch
  - privacy requirement violation
- Scoring priorities:
  - consent + privacy + boundaries + purpose fit
- Card should say:
  - “This match is based on clear expectations, high privacy preference, and respectful pacing.”

## Match card story model (anonymous-safe)
Each card should be generated from structured fields, not free-form ad hoc text.

### Card explanation structure
1. **Purpose label**: concise goal label.
2. **Compatibility story**: 1–2 sentence narrative tied to purpose and score components.
3. **Top reasons**: up to 3 bullets from strongest components.
4. **Aligned expectations**: one line on pace/privacy/consent.
5. **Boundaries note**: one reassurance sentence on mutual boundary respect.
6. **Suggested opener**: safe, respectful first message prompt.
7. **What remains hidden**: explicit privacy notice (name/contact/exact location hidden).

### What remains hidden (mandatory)
- legal identity fields
- contact details
- exact address/location precision
- explicit sensitive free-text disclosures

## Persian card examples (privacy-safe)

### Emotional connection
- **برچسب هدف:** ارتباط احساسی
- **داستان سازگاری:** سرعت نزدیک شدن و سبک ارتباطی شما برای شروعی آرام همسو است.
- **دلایل اصلی:**
  - ترجیح مشترک برای گفت‌وگوی محترمانه و کم‌فشار
  - هم‌راستایی در مرزگذاری و احترام متقابل
  - سطح قابل‌قبول از صمیمیت تدریجی
- **انتظار همسو:** هر دو طرف با ریتم ملایم و پاسخ‌گویی قابل‌پیش‌بینی راحت‌تر هستید.
- **یادداشت مرزها:** رعایت مرزها برای هر دو طرف غیرقابل‌چشم‌پوشی است.
- **شروع پیشنهادی:** «برای شروع یک گفت‌وگوی امن و راحت، ترجیح می‌دهید از چه موضوعی شروع کنیم؟»
- **موارد پنهان:** نام، اطلاعات تماس و موقعیت دقیق نمایش داده نمی‌شود.

### Travel/activity
- **برچسب هدف:** همراه سفر/فعالیت
- **داستان سازگاری:** سبک برنامه‌ریزی و نوع فعالیت موردعلاقه شما قابل‌هماهنگی است.
- **دلایل اصلی:**
  - هم‌پوشانی در نوع فعالیت
  - سازگاری در بازه زمانی و سرعت تصمیم‌گیری
  - تأکید مشترک بر ملاقات اولیه امن
- **انتظار همسو:** هر دو طرف ترجیح می‌دهید برنامه قبل از اجرا شفاف باشد.
- **یادداشت مرزها:** شروع در فضای عمومی و امن برای هر دو طرف مهم است.
- **شروع پیشنهادی:** «برای اولین برنامه، ترجیح می‌دهید یک فعالیت کوتاه و عمومی را انتخاب کنیم؟»
- **موارد پنهان:** نام، اطلاعات تماس و موقعیت دقیق نمایش داده نمی‌شود.

### Casual, non-committed
- **برچسب هدف:** ارتباط سبک و بدون تعهد بلندمدت
- **داستان سازگاری:** معرفی بر اساس شفافیت انتظارها، رازداری بالا و احترام به مرزها انجام شده است.
- **دلایل اصلی:**
  - هم‌راستایی در اهمیت حریم خصوصی
  - توافق در لحن محترمانه و رضایت‌محور
  - سازگاری در سرعت پیشروی رابطه
- **انتظار همسو:** هر دو طرف بر «شفاف، بدون فشار، قابل‌توقف» بودن ارتباط تأکید دارید.
- **یادداشت مرزها:** هر زمان عدم راحتی ایجاد شود، توقف گفت‌وگو کاملاً محترم است.
- **شروع پیشنهادی:** «برای اینکه گفت‌وگو راحت بماند، مایلید اول درباره مرزها و سطح راحتی صحبت کنیم؟»
- **موارد پنهان:** نام، اطلاعات تماس و موقعیت دقیق نمایش داده نمی‌شود.

### Support-based
- **برچسب هدف:** ارتباط حمایتی با انتظارهای شفاف
- **داستان سازگاری:** نوع حمایت موردنیاز و قابل‌ارائه شما با سطح شفافیت و حریم خصوصی مشابه هم‌راستا است.
- **دلایل اصلی:**
  - تطابق دوسویه نیاز و توان ارائه حمایت
  - توافق در قواعد ایمنی و مرزبندی
  - سازگاری در بازه زمانی موردنظر
- **انتظار همسو:** هر دو طرف بر شفافیت نقش‌ها و احترام متقابل تأکید دارید.
- **یادداشت مرزها:** هرگونه ادامه ارتباط منوط به رضایت دوطرفه و مرزهای روشن است.
- **شروع پیشنهادی:** «برای شروع شفاف، ترجیح می‌دهید ابتدا درباره حدود انتظارها و نحوه هماهنگی صحبت کنیم؟»
- **موارد پنهان:** نام، اطلاعات تماس و موقعیت دقیق نمایش داده نمی‌شود.

## Minimal implementation plan

### A) Catalog and keys
- Define Phase 2 key catalog for each goal cluster using namespace convention.
- Add i18n labels and descriptions for each key/value.

### B) Onboarding capture
- Extend onboarding question flow to collect new grouped keys.
- Persist in `user_goal_preferences` with existing structures.

### C) Scoring service upgrade
- Add cluster policy resolver.
- Add need/offer directional scorer.
- Add consent/privacy component and penalties.
- Keep fallback behavior for sparse optional data.

### D) Hard filter upgrade
- Add cluster-aware hard checks for `must.*`, privacy floors, role polarity.
- Continue writing deterministic reason codes.

### E) Card generation upgrade
- Generate purpose-aware explanation templates by cluster.
- Keep anonymization constraints intact.

### F) Verification fixtures
Add fixture coverage for:
- reciprocal need/offer correctness
- complementary role matching
- privacy strictness in sensitive goals
- hard-filter reason-code determinism
- card privacy leak prevention

## Implementation readiness and approval gate
Implementation should **not** begin until all of the following are reviewed and approved:

- [ ] key taxonomy is reviewed (all goal clusters)
- [ ] i18n wording is reviewed (including respectful-language constraints)
- [ ] card examples are reviewed (EN + FA)
- [ ] hard filters are agreed
- [ ] verification examples and expected outputs are agreed

## Optional future migrations (not required for initial rollout)
If product/admin needs runtime tuning without deploys, consider:

1. Add metadata columns to `goal_preference_definitions`:
   - `match_mode` (`similarity|complementary|need_offer|exact`)
   - `is_hard_filter`
   - `compatibility_map_json`

2. Add `goal_matching_strategies` table with versioned JSON profiles:
   - per-goal weights
   - hard-rule toggles
   - rollout metadata

Initial implementation can remain code-config based and move to DB-managed profiles later.

## Non-negotiable safety constraints
- Boundary mismatches override high compatibility score.
- Consent alignment is mandatory in sensitive clusters.
- No coercive or manipulative explanation phrasing.
- Progressive reveal only; no identifying payloads in anonymous stage.
- Users must always be able to pass/undo without pressure.

## Deliverable checklist for Phase 2 readiness
- [ ] Goal-cluster policy profiles committed
- [ ] New preference key catalog committed
- [ ] Onboarding save/load verified for new keys
- [ ] Scoring + hard-filter fixtures green
- [ ] Card explanation templates localized and privacy-reviewed
- [ ] Runtime telemetry hooks added for score component diagnostics
