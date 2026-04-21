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

2. **Relaxed nearby** (if still below target limit)
   - same `country_code`
   - same `region_code`
   - same `location_cell_l4`

3. **Broader fallback** (if still below target limit)
   - same `country_code`

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
