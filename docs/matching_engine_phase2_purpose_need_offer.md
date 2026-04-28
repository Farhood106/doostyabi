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

Examples:
- `seek.emotional_support_level`
- `offer.emotional_support_level`
- `must.boundary_respect`
- `privacy.discretion_level`
- `pace.response_cadence`

This keeps logic declarative through existing `goal_preference_definitions.pref_key`.

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
- need/offer 20
- consent/boundary 30
- privacy 20
- pace/duration 10
- availability/distance 5
- personality 0..5 optional

### Support-based relationship
- goal 15
- need/offer 35
- consent/boundary 20
- privacy 15
- pace/duration 10
- availability/distance 5
- personality 0..5 optional

## Match card explanation policy (anonymous-safe)
Each card explanation should answer:
1. Purpose of the introduction
2. Top 3–5 compatibility reasons
3. One expectation-alignment summary (pace/privacy/boundaries)
4. Optional single caution (only actionable, non-sensitive)
5. Suggested respectful opener

Do not expose identity or precision location data in card content.

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
