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

## Hard filters
Minimum enforced exclusions:
1. same user
2. blocked pair
3. inactive users
4. no active goal overlap
5. age preference mismatch (both directions)
6. coarse location mismatch
7. boundary conflicts
8. core lifestyle conflicts (smoking/drinking)

Output fields:
- `eligible_for_display`
- `rejection_reason_codes`

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

## Queue updates
- Writes to `match_candidate_queue` through upsert on `(user_id, candidate_user_id, goal_id)`.
- Rejected candidates store `rejection_reason_code` and short expiry (`+2 days`).
- Scored candidates store JSON breakdown and longer expiry (`+7 days`).
- Low scores are marked rejected with `low_compatibility_score`.

## No-match state logic
After per-user processing:
- if strong candidates (`score >= 70`) exist -> `searching`
- else if profile is weak (`about_me` or `looking_for` empty) -> `profile_improvement_suggested`
- else -> `expand_preferences_suggested`

State is persisted through `no_match_states` with active-state rollover.

## Cron usage
Example crontab every 10 minutes:

```cron
*/10 * * * * /usr/bin/php /home/USER/app/bin/cron_generate_candidates.php 50 0 200 >> /home/USER/logs/candidate_cron.log 2>&1
```

Arguments:
1. users batch limit
2. users batch offset
3. candidate limit per user
