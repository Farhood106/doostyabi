# SQL assumptions for auth + onboarding foundation

The app layer expects `database/schema.sql` to be applied.

## Required i18n keys (minimum)
Add these keys to `i18n_texts` for `fa` (and optional `en`):

- `app.name`
- `auth.register_title`
- `auth.login_title`
- `auth.logout`
- `auth.email`
- `auth.password`
- `auth.locale`
- `auth.register_submit`
- `auth.login_submit`
- `auth.invalid_credentials`
- `dashboard.title`
- `dashboard.description`
- `onboarding.profile_title`
- `onboarding.boundaries_title`
- `onboarding.availability_title`
- `onboarding.goals_title`
- `onboarding.birth_year`
- `onboarding.age_min_pref`
- `onboarding.age_max_pref`
- `onboarding.gender_identity`
- `onboarding.interested_in_gender`
- `onboarding.country_code`
- `onboarding.region_code`
- `onboarding.location_cell_l5`
- `onboarding.location_cell_l4`
- `onboarding.distance_radius_km`
- `onboarding.about_me`
- `onboarding.looking_for`
- `onboarding.boundary_key`
- `onboarding.boundary_value`
- `onboarding.boundary_importance`
- `onboarding.weekday`
- `onboarding.start_minute`
- `onboarding.end_minute`
- `common.save_continue`
- `common.finish`
- `validation.required`
- `validation.email`
- `validation.min`
- `validation.max`
- `validation.int`

- `security.invalid_csrf`
- `auth.email_taken`
- `common.unexpected_error`
- `validation.birth_year_range`
- `validation.age_range`
- `validation.age_order`
- `validation.radius_range`
- `validation.country_code`
- `validation.dimension_range`
- `validation.invalid_importance`
- `validation.weekday`
- `validation.time_range`
- `validation.time_order`
- `validation.goals_invalid`
- `onboarding.step_locked`
- `onboarding.profile_saved`
- `onboarding.boundaries_saved`
- `onboarding.availability_saved`
- `onboarding.completed`

## Efficiency note
Load all text for the active locale once per request and cache in static memory (already done in `Translator`).
For higher scale on shared hosting, add APCu or file cache invalidated by `i18n_texts.updated_at` checksum.
