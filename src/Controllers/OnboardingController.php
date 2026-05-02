<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\GoalRepository;
use App\Repositories\OnboardingRepository;
use App\Security\Csrf;
use App\Services\GoalQuestionCatalogService;
use App\Services\OnboardingProgressService;
use App\Validation\Validator;
use Throwable;

final class OnboardingController
{
    private const BOUNDARY_CATALOG = [
        'communication' => [
            'title_key' => 'onboarding.boundary_group.communication',
            'items' => [
                'communication.response_pace' => ['key' => 'communication', 'value' => 'response_pace'],
                'communication.respectful_tone' => ['key' => 'communication', 'value' => 'respectful_tone'],
            ],
        ],
        'privacy' => [
            'title_key' => 'onboarding.boundary_group.privacy',
            'items' => [
                'privacy.no_personal_details_early' => ['key' => 'privacy', 'value' => 'no_personal_details_early'],
                'privacy.no_recording_without_consent' => ['key' => 'privacy', 'value' => 'no_recording_without_consent'],
            ],
        ],
        'in_person_meeting' => [
            'title_key' => 'onboarding.boundary_group.in_person_meeting',
            'items' => [
                'in_person_meeting.public_place_first' => ['key' => 'in_person_meeting', 'value' => 'public_place_first'],
                'in_person_meeting.no_late_night_first' => ['key' => 'in_person_meeting', 'value' => 'no_late_night_first'],
            ],
        ],
        'emotional' => [
            'title_key' => 'onboarding.boundary_group.emotional',
            'items' => [
                'emotional.no_pressure_for_fast_attachment' => ['key' => 'emotional', 'value' => 'no_pressure_for_fast_attachment'],
                'emotional.honest_expectations' => ['key' => 'emotional', 'value' => 'honest_expectations'],
            ],
        ],
        'pacing' => [
            'title_key' => 'onboarding.boundary_group.pacing',
            'items' => [
                'pacing.step_by_step' => ['key' => 'pacing', 'value' => 'step_by_step'],
                'pacing.no_daily_contact_requirement' => ['key' => 'pacing', 'value' => 'no_daily_contact_requirement'],
            ],
        ],
        'social_media' => [
            'title_key' => 'onboarding.boundary_group.social_media',
            'items' => [
                'social_media.no_forced_follow' => ['key' => 'social_media', 'value' => 'no_forced_follow'],
                'social_media.no_posting_without_consent' => ['key' => 'social_media', 'value' => 'no_posting_without_consent'],
            ],
        ],
        'safety' => [
            'title_key' => 'onboarding.boundary_group.safety',
            'items' => [
                'safety.block_on_disrespect' => ['key' => 'safety', 'value' => 'block_on_disrespect'],
                'safety.end_chat_on_abuse' => ['key' => 'safety', 'value' => 'end_chat_on_abuse'],
            ],
        ],
        'intimacy_or_sensitive_content' => [
            'title_key' => 'onboarding.boundary_group.intimacy_or_sensitive_content',
            'items' => [
                'intimacy_or_sensitive_content.no_sensitive_media_early' => ['key' => 'intimacy_or_sensitive_content', 'value' => 'no_sensitive_media_early'],
                'intimacy_or_sensitive_content.respect_sensitive_topics' => ['key' => 'intimacy_or_sensitive_content', 'value' => 'respect_sensitive_topics'],
            ],
        ],
    ];

    private const AVAILABILITY_DAY_CATALOG = [
        'sat' => ['weekday' => 6, 'label_key' => 'onboarding.weekday.sat'],
        'sun' => ['weekday' => 0, 'label_key' => 'onboarding.weekday.sun'],
        'mon' => ['weekday' => 1, 'label_key' => 'onboarding.weekday.mon'],
        'tue' => ['weekday' => 2, 'label_key' => 'onboarding.weekday.tue'],
        'wed' => ['weekday' => 3, 'label_key' => 'onboarding.weekday.wed'],
        'thu' => ['weekday' => 4, 'label_key' => 'onboarding.weekday.thu'],
        'fri' => ['weekday' => 5, 'label_key' => 'onboarding.weekday.fri'],
    ];

    private const AVAILABILITY_TIME_BLOCK_CATALOG = [
        'morning' => ['label_key' => 'onboarding.time_block.morning', 'start_minute' => 8 * 60, 'end_minute' => 12 * 60],
        'noon' => ['label_key' => 'onboarding.time_block.noon', 'start_minute' => 12 * 60, 'end_minute' => 16 * 60],
        'evening' => ['label_key' => 'onboarding.time_block.evening', 'start_minute' => 16 * 60, 'end_minute' => 20 * 60],
        'night' => ['label_key' => 'onboarding.time_block.night', 'start_minute' => 20 * 60, 'end_minute' => (23 * 60) + 30],
        // Iran weekend-friendly mapping for MVP: Thursday + Friday.
        'weekend' => ['label_key' => 'onboarding.time_block.weekend', 'start_minute' => 10 * 60, 'end_minute' => 22 * 60, 'weekdays' => [4, 5]],
        // "Anytime" means broad daily window; when no day is selected, all week days are used.
        'anytime' => ['label_key' => 'onboarding.time_block.anytime', 'start_minute' => 8 * 60, 'end_minute' => (23 * 60) + 30],
    ];

    private const GOAL_GROUP_BY_SLUG = [
        'friendly_conversation' => 'conversation',
        'casual_connection' => 'conversation',
        'emotional_connection' => 'emotional',
        'long_term_relationship' => 'emotional',
        'travel_companion' => 'travel_events',
        'event_companion' => 'travel_events',
        'sports_companion' => 'sports_activity',
        'social_activity_partner' => 'sports_activity',
        'project_collaboration' => 'collaboration',
        'co_living' => 'collaboration',
        'personal_growth_connection' => 'personal_growth',
    ];

    private const IRAN_LOCATIONS = [
        'tehran' => ['fa' => 'تهران', 'en' => 'Tehran', 'cities' => [
            'tehran' => ['fa' => 'تهران', 'en' => 'Tehran'],
            'karaj' => ['fa' => 'کرج', 'en' => 'Karaj'],
            'shemiran' => ['fa' => 'شمیرانات', 'en' => 'Shemiranat'],
        ]],
        'isfahan' => ['fa' => 'اصفهان', 'en' => 'Isfahan', 'cities' => [
            'isfahan' => ['fa' => 'اصفهان', 'en' => 'Isfahan'],
            'kashan' => ['fa' => 'کاشان', 'en' => 'Kashan'],
            'najafabad' => ['fa' => 'نجف‌آباد', 'en' => 'Najafabad'],
        ]],
        'fars' => ['fa' => 'فارس', 'en' => 'Fars', 'cities' => [
            'shiraz' => ['fa' => 'شیراز', 'en' => 'Shiraz'],
            'marvdasht' => ['fa' => 'مرودشت', 'en' => 'Marvdasht'],
            'jahrom' => ['fa' => 'جهرم', 'en' => 'Jahrom'],
        ]],
        'khorasan_razavi' => ['fa' => 'خراسان رضوی', 'en' => 'Khorasan Razavi', 'cities' => [
            'mashhad' => ['fa' => 'مشهد', 'en' => 'Mashhad'],
            'neyshabur' => ['fa' => 'نیشابور', 'en' => 'Neyshabur'],
            'sabzevar' => ['fa' => 'سبزوار', 'en' => 'Sabzevar'],
        ]],
        'east_azerbaijan' => ['fa' => 'آذربایجان شرقی', 'en' => 'East Azerbaijan', 'cities' => [
            'tabriz' => ['fa' => 'تبریز', 'en' => 'Tabriz'],
            'maragheh' => ['fa' => 'مراغه', 'en' => 'Maragheh'],
            'marand' => ['fa' => 'مرند', 'en' => 'Marand'],
        ]],
        'khuzestan' => ['fa' => 'خوزستان', 'en' => 'Khuzestan', 'cities' => [
            'ahvaz' => ['fa' => 'اهواز', 'en' => 'Ahvaz'],
            'abadan' => ['fa' => 'آبادان', 'en' => 'Abadan'],
            'dezful' => ['fa' => 'دزفول', 'en' => 'Dezful'],
        ]],
    ];

    public function __construct(private readonly App $app) {}

    public function showProfile(Request $request): void
    {
        View::render('onboarding/profile', $this->profileViewData());
    }

    public function saveProfile(Request $request): never
    {
        $this->validateCsrf($request, '/onboarding/profile');

        $data = [
            'birth_year' => (string)$request->input('birth_year'),
            'age_min_pref' => (string)$request->input('age_min_pref'),
            'age_max_pref' => (string)$request->input('age_max_pref'),
            'gender_identity' => trim((string)$request->input('gender_identity')),
            'interested_in_gender' => trim((string)$request->input('interested_in_gender')),
            'about_me' => trim((string)$request->input('about_me')),
            'looking_for' => trim((string)$request->input('looking_for')),
            'social_energy' => (string)$request->input('social_energy'),
            'communication_style' => (string)$request->input('communication_style'),
            'emotional_openness' => (string)$request->input('emotional_openness'),
            'relationship_pace' => (string)$request->input('relationship_pace'),
            'independence_level' => (string)$request->input('independence_level', '3'),
            'boundary_sensitivity' => (string)$request->input('boundary_sensitivity'),
            'structure_vs_spontaneity' => (string)$request->input('structure_vs_spontaneity', '3'),
            'smoking_preference' => (string)$request->input('smoking_preference'),
            'drinking_preference' => (string)$request->input('drinking_preference'),
            'activity_level' => (string)$request->input('activity_level'),
            'province' => (string)$request->input('province'),
            'city' => (string)$request->input('city'),
            'distance_radius_km' => (string)$request->input('distance_radius_km', '30'),
        ];

        $data += $this->deriveInternalLocation($data['province'], $data['city']);

        $_SESSION['_old'] = $data;
        $errors = $this->validateProfile($data);

        if ($errors !== []) {
            flash('errors', $errors);
            Response::redirect('/onboarding/profile');
        }

        try {
            $uid = $this->userId();
            $persistData = $data;
            unset($persistData['province'], $persistData['city']);
            $this->app->make(OnboardingRepository::class)->saveProfile($uid, $persistData);
        } catch (Throwable $e) {
            $this->logException($e, 'onboarding.profile');
            flash('message', 'common.unexpected_error');
            Response::redirect('/onboarding/profile');
        }

        flash('message', 'onboarding.profile_saved');
        $this->clearOldInputs([
            'birth_year','age_min_pref','age_max_pref','gender_identity','interested_in_gender','about_me','looking_for',
            'social_energy','communication_style','emotional_openness','relationship_pace','independence_level',
            'boundary_sensitivity','structure_vs_spontaneity','smoking_preference','drinking_preference','activity_level',
            'province','city','distance_radius_km','country_code','region_code','location_cell_l5','location_cell_l4',
        ]);
        $next = $this->app->make(OnboardingProgressService::class)->firstIncompleteStep($this->userId());
        if ($next === 'done') {
            Response::redirect('/dashboard');
        }
        Response::redirect('/onboarding/' . $next);
    }

    public function showBoundaries(Request $request): void
    {
        $this->guardStep('boundaries');
        View::render('onboarding/boundaries', $this->boundaryViewData());
    }

    public function saveBoundaries(Request $request): never
    {
        $this->guardStep('boundaries');
        $this->validateCsrf($request, '/onboarding/boundaries');

        $selectedBoundaryIds = array_values(array_unique((array)($request->input('boundary_ids') ?? [])));
        $importanceMap = (array)($request->input('importance') ?? []);
        $_SESSION['_old']['boundary_ids'] = $selectedBoundaryIds;
        $_SESSION['_old']['importance'] = $importanceMap;

        $rows = [];
        $errors = [];
        $allowedImportance = ['required', 'preferred', 'avoid'];
        $catalogItems = $this->flatBoundaryItems();

        foreach ($selectedBoundaryIds as $boundaryId) {
            $id = trim((string)$boundaryId);
            if ($id === '') {
                continue;
            }

            if (!isset($catalogItems[$id])) {
                $errors['boundary_ids'][] = 'validation.boundary_invalid';
                continue;
            }

            $imp = (string)($importanceMap[$id] ?? 'preferred');
            if (!in_array($imp, $allowedImportance, true)) {
                $errors['importance'][] = 'validation.invalid_importance';
            }

            $rows[] = [
                'key' => $catalogItems[$id]['key'],
                'value' => $catalogItems[$id]['value'],
                'importance' => $imp,
            ];
        }

        if ($rows === []) {
            $errors['boundary_ids'][] = 'validation.boundary_selection_required';
        }

        if ($errors !== []) {
            flash('errors', $errors);
            Response::redirect('/onboarding/boundaries');
        }

        try {
            $this->app->make(OnboardingRepository::class)->replaceBoundaries($this->userId(), $rows);
        } catch (Throwable $e) {
            $this->logException($e, 'onboarding.boundaries');
            flash('message', 'common.unexpected_error');
            Response::redirect('/onboarding/boundaries');
        }

        flash('message', 'onboarding.boundaries_saved');
        $this->clearOldInputs(['boundary_ids', 'importance']);
        $next = $this->app->make(OnboardingProgressService::class)->firstIncompleteStep($this->userId());
        if ($next === 'done') {
            Response::redirect('/dashboard');
        }
        Response::redirect('/onboarding/' . $next);
    }

    public function showAvailability(Request $request): void
    {
        $this->guardStep('availability');
        View::render('onboarding/availability', $this->availabilityViewData());
    }

    public function saveAvailability(Request $request): never
    {
        $this->guardStep('availability');
        $this->validateCsrf($request, '/onboarding/availability');

        $dayKeys = array_values(array_unique((array)($request->input('day_keys') ?? [])));
        $timeBlockKeys = array_values(array_unique((array)($request->input('time_block_keys') ?? [])));
        $_SESSION['_old']['day_keys'] = $dayKeys;
        $_SESSION['_old']['time_block_keys'] = $timeBlockKeys;

        $rows = [];
        $errors = [];
        $daysCatalog = self::AVAILABILITY_DAY_CATALOG;
        $blocksCatalog = self::AVAILABILITY_TIME_BLOCK_CATALOG;

        if ($dayKeys === [] && $timeBlockKeys === []) {
            $errors['availability'][] = 'validation.availability_selection_required';
        }

        $selectedDays = [];
        foreach ($dayKeys as $key) {
            $id = trim((string)$key);
            if (!isset($daysCatalog[$id])) {
                $errors['day_keys'][] = 'validation.weekday';
                continue;
            }
            $selectedDays[] = (int)$daysCatalog[$id]['weekday'];
        }
        $selectedDays = array_values(array_unique($selectedDays));

        foreach ($timeBlockKeys as $key) {
            $id = trim((string)$key);
            if (!isset($blocksCatalog[$id])) {
                $errors['time_block_keys'][] = 'validation.time_range';
                continue;
            }

            $block = $blocksCatalog[$id];
            $targetDays = [];
            if (isset($block['weekdays'])) {
                $targetDays = (array)$block['weekdays'];
            } elseif ($id === 'anytime') {
                $targetDays = $selectedDays !== [] ? $selectedDays : array_values(array_map(static fn(array $d): int => (int)$d['weekday'], $daysCatalog));
            } else {
                $targetDays = $selectedDays;
            }

            if ($targetDays === []) {
                $errors['day_keys'][] = 'validation.availability_day_required';
                continue;
            }

            foreach ($targetDays as $weekday) {
                $rows[] = [
                    'weekday' => (int)$weekday,
                    'start_minute' => (int)$block['start_minute'],
                    'end_minute' => (int)$block['end_minute'],
                    'timezone_name' => 'Asia/Tehran',
                ];
            }
        }

        if ($timeBlockKeys === []) {
            $errors['time_block_keys'][] = 'validation.availability_block_required';
        }

        if ($rows === [] && $errors === []) {
            $errors['availability'][] = 'validation.availability_selection_required';
        }

        if ($errors !== []) {
            flash('errors', $errors);
            Response::redirect('/onboarding/availability');
        }

        $rows = $this->deduplicateAvailabilityRows($rows);

        try {
            $this->app->make(OnboardingRepository::class)->replaceAvailability($this->userId(), $rows);
        } catch (Throwable $e) {
            $this->logException($e, 'onboarding.availability');
            flash('message', 'common.unexpected_error');
            Response::redirect('/onboarding/availability');
        }

        flash('message', 'onboarding.availability_saved');
        $this->clearOldInputs(['day_keys', 'time_block_keys']);
        $next = $this->app->make(OnboardingProgressService::class)->firstIncompleteStep($this->userId());
        if ($next === 'done') {
            Response::redirect('/dashboard');
        }
        Response::redirect('/onboarding/' . $next);
    }

    public function showGoals(Request $request): void
    {
        $this->guardStep('goals');
        $goals = $this->app->make(GoalRepository::class)->activeGoals();
        View::render('onboarding/goals', $this->goalsViewData($goals));
    }

    public function saveGoals(Request $request): never
    {
        $this->guardStep('goals');
        $this->validateCsrf($request, '/onboarding/goals');
        $goalIds = array_values(array_unique(array_map('intval', (array)$request->input('goal_ids', []))));
        $primaryGoalId = (int)$request->input('primary_goal_id', 0);
        $_SESSION['_old']['goal_ids'] = $goalIds;
        $_SESSION['_old']['primary_goal_id'] = $primaryGoalId;

        if (count($goalIds) === 0) {
            flash('errors', ['goal_ids' => ['validation.goal_selection_required']]);
            Response::redirect('/onboarding/goals');
        }

        if ($primaryGoalId <= 0 || !in_array($primaryGoalId, $goalIds, true)) {
            flash('errors', ['primary_goal_id' => ['validation.primary_goal_required']]);
            Response::redirect('/onboarding/goals');
        }

        $repo = $this->app->make(OnboardingRepository::class);
        $activeGoalIds = $repo->activeGoalIds($goalIds);

        if (count($activeGoalIds) !== count($goalIds)) {
            flash('errors', ['goal_ids' => ['validation.goals_invalid']]);
            Response::redirect('/onboarding/goals');
        }

        if (!in_array($primaryGoalId, $activeGoalIds, true)) {
            flash('errors', ['primary_goal_id' => ['validation.primary_goal_required']]);
            Response::redirect('/onboarding/goals');
        }

        try {
            $uid = $this->userId();
            $repo->replaceGoals($uid, $goalIds, $primaryGoalId);
        } catch (Throwable $e) {
            $this->logException($e, 'onboarding.goals');
            flash('message', 'common.unexpected_error');
            Response::redirect('/onboarding/goals');
        }

        $this->clearOldInputs(['goal_ids', 'primary_goal_id']);
        flash('message', 'onboarding.goals_saved');
        Response::redirect('/onboarding/goal-questions');
    }

    public function showGoalQuestions(Request $request): void
    {
        $this->guardStep('goal-questions');
        $goals = $this->app->make(GoalRepository::class)->activeGoals();
        View::render('onboarding/goal_questions', $this->goalQuestionsViewData($goals));
    }

    public function saveGoalQuestions(Request $request): never
    {
        $this->guardStep('goal-questions');
        $this->validateCsrf($request, '/onboarding/goal-questions');
        $goalPreferences = (array)($request->input('goal_pref') ?? []);
        $_SESSION['_old']['goal_pref'] = $goalPreferences;

        $uid = $this->userId();
        $repo = $this->app->make(OnboardingRepository::class);
        $goalIds = $repo->getActiveGoalIdsForUser($uid);
        if ($goalIds === []) {
            flash('message', 'validation.goal_selection_required');
            Response::redirect('/onboarding/goals');
        }

        $primaryGoalId = $repo->getPrimaryGoalIdForUser($uid) ?? (int)($goalIds[0] ?? 0);
        $definitions = $this->goalQuestionDefinitionsForSelectedGoals($goalIds, $primaryGoalId);
        $goalSlugById = [];
        foreach ($this->app->make(GoalRepository::class)->activeGoals() as $goal) {
            $goalSlugById[(int)($goal['id'] ?? 0)] = (string)($goal['slug'] ?? '');
        }
        $primarySlug = (string)($goalSlugById[$primaryGoalId] ?? '');
        if ($definitions === [] && $this->app->make(GoalQuestionCatalogService::class)->isSensitiveGoalSlug($primarySlug)) {
            flash('errors', ['goal_pref' => ['validation.goal_pref_required']]);
            Response::redirect('/onboarding/goal-questions');
        }
        $prefErrors = $this->validateGoalPreferenceAnswers($definitions, $goalPreferences, $goalSlugById);
        if ($prefErrors !== []) {
            flash('errors', $prefErrors);
            Response::redirect('/onboarding/goal-questions');
        }

        try {
            $repo->replaceGoalPreferenceValues($uid, $goalPreferences);
            $repo->markProfileCompleted($uid);
        } catch (Throwable $e) {
            $this->logException($e, 'onboarding.goal_questions');
            flash('message', 'common.unexpected_error');
            Response::redirect('/onboarding/goal-questions');
        }

        $this->clearOldInputs(['goal_pref']);
        flash('message', 'onboarding.completed');
        Response::redirect('/dashboard');
    }

    private function validateProfile(array $data): array
    {
        $v = new Validator();
        $v->validate($data, [
            'birth_year' => 'required|int',
            'age_min_pref' => 'required|int',
            'age_max_pref' => 'required|int',
            'distance_radius_km' => 'required|int',
            'province' => 'required|max:40',
            'city' => 'required|max:40',
            'social_energy' => 'required|int',
            'communication_style' => 'required|int',
            'emotional_openness' => 'required|int',
            'relationship_pace' => 'required|int',
            'independence_level' => 'required|int',
            'boundary_sensitivity' => 'required|int',
            'structure_vs_spontaneity' => 'required|int',
            'activity_level' => 'required|max:20',
        ]);

        $errors = $v->errors();
        $year = (int)$data['birth_year'];
        $currentYear = (int)date('Y');

        if ($year < 1940 || $year > ($currentYear - 18)) {
            $errors['birth_year'][] = 'validation.birth_year_range';
        }

        $ageMin = (int)$data['age_min_pref'];
        $ageMax = (int)$data['age_max_pref'];
        if ($ageMin < 18 || $ageMin > 99) $errors['age_min_pref'][] = 'validation.age_range';
        if ($ageMax < 18 || $ageMax > 99) $errors['age_max_pref'][] = 'validation.age_range';
        if ($ageMin > $ageMax) $errors['age_min_pref'][] = 'validation.age_order';

        $radius = (int)$data['distance_radius_km'];
        if ($radius < 1 || $radius > 500) {
            $errors['distance_radius_km'][] = 'validation.radius_range';
        }

        if (($data['country_code'] ?? '') !== 'IR') {
            $errors['province'][] = 'validation.invalid_iran_location';
        }

        $province = (string)$data['province'];
        $city = (string)$data['city'];
        if (!$this->isValidIranLocation($province, $city)) {
            $errors['city'][] = 'validation.invalid_iran_location';
        }

        foreach (['social_energy','communication_style','emotional_openness','relationship_pace','independence_level','boundary_sensitivity','structure_vs_spontaneity'] as $field) {
            $value = (int)$data[$field];
            if ($value < 1 || $value > 5) {
                $errors[$field][] = 'validation.dimension_range';
            }
        }

        if (!in_array((string)$data['activity_level'], ['low', 'moderate', 'high'], true)) {
            $errors['activity_level'][] = 'validation.activity_level';
        }
        if (!in_array((string)$data['smoking_preference'], ['no', 'yes', 'occasionally', 'prefer_not'], true)) {
            $errors['smoking_preference'][] = 'validation.preference_invalid';
        }
        if (!in_array((string)$data['drinking_preference'], ['no', 'yes', 'occasionally', 'prefer_not'], true)) {
            $errors['drinking_preference'][] = 'validation.preference_invalid';
        }

        return $errors;
    }

    private function validateCsrf(Request $request, string $redirect): void
    {
        $csrf = $this->app->make(Csrf::class);
        if (!$csrf->validate((string)$request->input('_token'))) {
            flash('message', 'security.invalid_csrf');
            Response::redirect($redirect);
        }
    }

    private function guardStep(string $step): void
    {
        $uid = $this->userId();
        $progress = $this->app->make(OnboardingProgressService::class);

        if (!$progress->canAccessStep($uid, $step)) {
            $first = $progress->firstIncompleteStep($uid);
            flash('message', 'onboarding.step_locked');
            Response::redirect('/onboarding/' . $first);
        }
    }

    private function userId(): int
    {
        return (int)$this->app->make(AuthService::class)->userId();
    }

    private function viewData(): array
    {
        return [
            'errors' => flashGet('errors', []),
        ];
    }

    private function profileViewData(): array
    {
        $saved = $this->app->make(OnboardingRepository::class)->getProfileForUser($this->userId()) ?? [];
        $savedProvince = (string)($saved['region_code'] ?? 'tehran');
        $savedCity = (string)($saved['location_cell_l5'] ?? 'tehran');
        if (!$this->isValidIranLocation($savedProvince, $savedCity)) {
            $savedProvince = 'tehran';
            $savedCity = 'tehran';
        }

        $form = [
            'birth_year' => (string)($saved['birth_year'] ?? ''),
            'age_min_pref' => (string)($saved['age_min_pref'] ?? '23'),
            'age_max_pref' => (string)($saved['age_max_pref'] ?? '35'),
            'gender_identity' => (string)($saved['gender_identity'] ?? 'woman'),
            'interested_in_gender' => (string)($saved['interested_in_gender'] ?? ''),
            'about_me' => (string)($saved['about_me'] ?? ''),
            'looking_for' => (string)($saved['looking_for'] ?? ''),
            'social_energy' => (string)($saved['social_energy'] ?? '3'),
            'communication_style' => (string)($saved['communication_style'] ?? '3'),
            'emotional_openness' => (string)($saved['emotional_openness'] ?? '3'),
            'relationship_pace' => (string)($saved['relationship_pace'] ?? '3'),
            'independence_level' => (string)($saved['independence_level'] ?? '3'),
            'boundary_sensitivity' => (string)($saved['boundary_sensitivity'] ?? '3'),
            'structure_vs_spontaneity' => (string)($saved['structure_vs_spontaneity'] ?? '3'),
            'smoking_preference' => (string)($saved['smoking_preference'] ?? 'no'),
            'drinking_preference' => (string)($saved['drinking_preference'] ?? 'no'),
            'activity_level' => (string)($saved['activity_level'] ?? 'moderate'),
            'province' => $savedProvince,
            'city' => $savedCity,
            'distance_radius_km' => (string)($saved['distance_radius_km'] ?? '30'),
        ];

        foreach (array_keys($form) as $field) {
            if ($this->hasOldInput($field)) {
                $form[$field] = (string)$this->oldInput($field, $form[$field]);
            }
        }

        $locale = currentLocale();
        $selectedProvince = (string)$form['province'];
        if (!isset(self::IRAN_LOCATIONS[$selectedProvince])) {
            $selectedProvince = 'tehran';
        }
        if (!isset(self::IRAN_LOCATIONS[$selectedProvince]['cities'][(string)$form['city']])) {
            $form['city'] = array_key_first(self::IRAN_LOCATIONS[$selectedProvince]['cities']) ?: 'tehran';
        }
        $form['province'] = $selectedProvince;

        $provinces = [];
        foreach (self::IRAN_LOCATIONS as $slug => $row) {
            $provinces[] = [
                'value' => $slug,
                'label' => $row[$locale] ?? $row['fa'],
            ];
        }

        $cities = [];
        foreach (self::IRAN_LOCATIONS[$selectedProvince]['cities'] as $slug => $row) {
            $cities[] = [
                'value' => $slug,
                'label' => $row[$locale] ?? $row['fa'],
            ];
        }

        return $this->viewData() + [
            'provinces' => $provinces,
            'cities' => $cities,
            'form' => $form,
        ];
    }

    private function boundaryViewData(): array
    {
        $savedRows = $this->app->make(OnboardingRepository::class)->getBoundariesForUser($this->userId());
        $flat = $this->flatBoundaryItems();
        $idByPair = [];
        foreach ($flat as $id => $payload) {
            $idByPair[$payload['key'] . '|' . $payload['value']] = $id;
        }

        $savedSelected = [];
        $savedImportance = [];
        foreach ($savedRows as $row) {
            $token = (string)$row['boundary_key'] . '|' . (string)$row['boundary_value'];
            if (!isset($idByPair[$token])) {
                continue;
            }
            $id = $idByPair[$token];
            $savedSelected[] = $id;
            $savedImportance[$id] = (string)$row['importance'];
        }

        $selected = $this->hasOldInput('boundary_ids')
            ? array_values(array_unique(array_map(static fn(mixed $v): string => trim((string)$v), (array)$this->oldInput('boundary_ids', []))))
            : $savedSelected;
        $importance = $this->hasOldInput('importance')
            ? (array)$this->oldInput('importance', [])
            : $savedImportance;

        $groups = [];
        foreach (self::BOUNDARY_CATALOG as $groupKey => $group) {
            $items = [];
            foreach ($group['items'] as $id => $payload) {
                $items[] = [
                    'id' => $id,
                    'label_key' => 'onboarding.boundary_item.' . $id,
                    'key' => $payload['key'],
                    'value' => $payload['value'],
                ];
            }
            $groups[] = [
                'group_key' => $groupKey,
                'title_key' => $group['title_key'],
                'items' => $items,
            ];
        }

        return $this->viewData() + [
            'boundaryGroups' => $groups,
            'selectedBoundaryIds' => $selected,
            'selectedBoundaryImportance' => $importance,
        ];
    }

    private function availabilityViewData(): array
    {
        $savedRows = $this->app->make(OnboardingRepository::class)->getAvailabilityForUser($this->userId());
        $reverseDay = [];
        foreach (self::AVAILABILITY_DAY_CATALOG as $id => $item) {
            $reverseDay[(int)$item['weekday']] = $id;
        }

        $savedDays = [];
        $rowsByToken = [];
        foreach ($savedRows as $row) {
            $weekday = (int)$row['weekday'];
            if (isset($reverseDay[$weekday])) {
                $savedDays[] = $reverseDay[$weekday];
            }
            $token = $weekday . '|' . (int)$row['start_minute'] . '|' . (int)$row['end_minute'];
            $rowsByToken[$token] = true;
        }
        $savedDays = array_values(array_unique($savedDays));

        $savedBlocks = [];
        if (isset($rowsByToken['4|600|1320'], $rowsByToken['5|600|1320'])) {
            $savedBlocks[] = 'weekend';
            unset($rowsByToken['4|600|1320'], $rowsByToken['5|600|1320']);
        }

        foreach (array_keys($rowsByToken) as $token) {
            $parts = explode('|', $token);
            $start = (int)($parts[1] ?? -1);
            $end = (int)($parts[2] ?? -1);
            foreach (self::AVAILABILITY_TIME_BLOCK_CATALOG as $blockId => $block) {
                if ($blockId === 'weekend') {
                    continue;
                }
                if ((int)$block['start_minute'] === $start && (int)$block['end_minute'] === $end) {
                    $savedBlocks[] = $blockId;
                    break;
                }
            }
        }
        $savedBlocks = array_values(array_unique($savedBlocks));

        $selectedDays = $this->hasOldInput('day_keys')
            ? array_values(array_unique(array_map(static fn(mixed $v): string => trim((string)$v), (array)$this->oldInput('day_keys', []))))
            : $savedDays;
        $selectedBlocks = $this->hasOldInput('time_block_keys')
            ? array_values(array_unique(array_map(static fn(mixed $v): string => trim((string)$v), (array)$this->oldInput('time_block_keys', []))))
            : $savedBlocks;

        $days = [];
        foreach (self::AVAILABILITY_DAY_CATALOG as $id => $item) {
            $days[] = [
                'id' => $id,
                'label_key' => (string)$item['label_key'],
            ];
        }

        $blocks = [];
        foreach (self::AVAILABILITY_TIME_BLOCK_CATALOG as $id => $item) {
            $blocks[] = [
                'id' => $id,
                'label_key' => (string)$item['label_key'],
            ];
        }

        return $this->viewData() + [
            'availabilityDays' => $days,
            'availabilityBlocks' => $blocks,
            'selectedDayKeys' => $selectedDays,
            'selectedTimeBlockKeys' => $selectedBlocks,
        ];
    }

    private function goalsViewData(array $goals): array
    {
        $repo = $this->app->make(OnboardingRepository::class);
        $savedGoalIds = $repo->getActiveGoalIdsForUser($this->userId());
        $selectedGoalIds = $this->hasOldInput('goal_ids')
            ? array_values(array_unique(array_map('intval', (array)$this->oldInput('goal_ids', []))))
            : $savedGoalIds;
        $selectedPrimaryGoalId = $this->hasOldInput('primary_goal_id')
            ? (int)$this->oldInput('primary_goal_id', 0)
            : (int)($repo->getPrimaryGoalIdForUser($this->userId()) ?? 0);

        $groups = [];
        foreach ($goals as $goal) {
            $slug = (string)($goal['slug'] ?? '');
            $groupKey = self::GOAL_GROUP_BY_SLUG[$slug] ?? 'conversation';
            $groups[$groupKey][] = $goal;
        }

        $ordered = [];
        foreach (['conversation', 'emotional', 'travel_events', 'sports_activity', 'collaboration', 'personal_growth'] as $key) {
            if (!isset($groups[$key])) {
                continue;
            }
            $ordered[] = [
                'group_key' => $key,
                'title_key' => 'onboarding.goal_group.' . $key,
                'goals' => $groups[$key],
            ];
        }

        return $this->viewData() + [
            'goalGroups' => $ordered,
            'selectedGoalIds' => $selectedGoalIds,
            'selectedPrimaryGoalId' => $selectedPrimaryGoalId,
        ];
    }

    private function goalQuestionsViewData(array $goals): array
    {
        $repo = $this->app->make(OnboardingRepository::class);
        $selectedGoalIds = $repo->getActiveGoalIdsForUser($this->userId());
        $primaryGoalId = $repo->getPrimaryGoalIdForUser($this->userId()) ?? (int)($selectedGoalIds[0] ?? 0);
        $definitions = $this->goalQuestionDefinitionsForSelectedGoals($selectedGoalIds, $primaryGoalId);
        $savedPrefValues = $repo->getGoalPreferenceValuesForUser($this->userId());
        $prefValues = $this->hasOldInput('goal_pref') ? (array)$this->oldInput('goal_pref', []) : $savedPrefValues;

        $goalTitleById = [];
        $goalSlugById = [];
        foreach ($goals as $goal) {
            $goalId = (int)($goal['id'] ?? 0);
            if ($goalId <= 0) {
                continue;
            }
            $goalTitleById[$goalId] = (string)($goal['title_key'] ?? 'onboarding.goals_title');
            $goalSlugById[$goalId] = (string)($goal['slug'] ?? '');
        }

        return $this->viewData() + [
            'selectedGoalIds' => $selectedGoalIds,
            'primaryGoalId' => $primaryGoalId,
            'goalPreferenceDefinitions' => $definitions,
            'goalPreferenceValues' => $prefValues,
            'goalTitleById' => $goalTitleById,
            'goalSlugById' => $goalSlugById,
        ];
    }

    private function goalQuestionDefinitionsForSelectedGoals(array $goalIds, ?int $primaryGoalId = null): array
    {
        if ($goalIds === []) {
            return [];
        }

        $repo = $this->app->make(OnboardingRepository::class);
        $activeGoals = $this->app->make(GoalRepository::class)->activeGoals();
        $goalSlugById = [];
        foreach ($activeGoals as $goal) {
            $goalSlugById[(int)$goal['id']] = (string)($goal['slug'] ?? '');
        }

        // MVP: ask shared baseline + primary-goal questions only.
        $primaryGoalId = (int)($primaryGoalId ?? (int)$goalIds[0]);
        $primarySlug = $goalSlugById[$primaryGoalId] ?? '';
        $catalog = $this->app->make(GoalQuestionCatalogService::class);
        $allowedKeys = $catalog->orderedKeysForPrimaryGoalSlug($primarySlug);
        $defs = $repo->goalPreferenceDefinitions([$primaryGoalId]);

        if ($allowedKeys === []) {
            return $defs;
        }

        $defsByKey = [];
        foreach ($defs as $def) {
            $defsByKey[(string)($def['pref_key'] ?? '')] = $def;
        }
        $ordered = [];
        foreach ($allowedKeys as $key) {
            if (isset($defsByKey[$key])) {
                $ordered[] = $defsByKey[$key];
            }
        }

        return $ordered;
    }

    private function validateGoalPreferenceAnswers(array $definitions, array $input, array $goalSlugById): array
    {
        $errors = [];
        $catalog = $this->app->make(GoalQuestionCatalogService::class);
        foreach ($definitions as $def) {
            $goalId = (int)$def['goal_id'];
            $prefKey = (string)$def['pref_key'];
            $isRequired = (int)($def['is_required'] ?? 0) === 1;
            $allowed = array_values(array_filter((array)($def['allowed_values'] ?? []), static fn(mixed $v): bool => is_string($v) && trim($v) !== ''));
            $inputType = (string)($def['input_type'] ?? 'select');
            $rawValue = $input[$goalId][$prefKey] ?? null;
            $isSensitiveGoal = $catalog->isSensitiveGoalSlug((string)($goalSlugById[$goalId] ?? ''));

            if ($inputType === 'multiselect') {
                $values = array_values(array_filter(array_map(static fn(mixed $v): string => trim((string)$v), (array)$rawValue), static fn(string $v): bool => $v !== ''));
                if ($values === []) {
                    if ($isRequired) {
                        $errors["goal_pref.{$goalId}.{$prefKey}"][] = 'validation.goal_pref_required';
                    }
                    continue;
                }
                if ($allowed !== []) {
                    foreach ($values as $value) {
                        if (!in_array($value, $allowed, true)) {
                            $errors["goal_pref.{$goalId}.{$prefKey}"][] = 'validation.goal_pref_invalid';
                            break;
                        }
                    }
                }
                continue;
            }

            $value = trim((string)$rawValue);
            if ($value === '') {
                if ($isRequired) {
                    $errors["goal_pref.{$goalId}.{$prefKey}"][] = 'validation.goal_pref_required';
                }
                continue;
            }

            if ($allowed !== [] && !in_array($value, $allowed, true)) {
                $errors["goal_pref.{$goalId}.{$prefKey}"][] = 'validation.goal_pref_invalid';
                continue;
            }

            if ($isSensitiveGoal && $catalog->isStrictSensitiveKey($prefKey) && in_array($value, ['not_sure', 'unsure'], true)) {
                $errors["goal_pref.{$goalId}.{$prefKey}"][] = 'validation.goal_pref_sensitive_explicit_required';
            }
        }

        return $errors;
    }

    private function flatBoundaryItems(): array
    {
        $flat = [];
        foreach (self::BOUNDARY_CATALOG as $group) {
            foreach ($group['items'] as $id => $payload) {
                $flat[$id] = $payload;
            }
        }
        return $flat;
    }

    private function deduplicateAvailabilityRows(array $rows): array
    {
        $unique = [];
        foreach ($rows as $row) {
            $token = implode('|', [
                (string)$row['weekday'],
                (string)$row['start_minute'],
                (string)$row['end_minute'],
                (string)$row['timezone_name'],
            ]);
            $unique[$token] = $row;
        }

        return array_values($unique);
    }

    private function hasOldInput(string $key): bool
    {
        return isset($_SESSION['_old']) && array_key_exists($key, $_SESSION['_old']);
    }

    private function oldInput(string $key, mixed $default = null): mixed
    {
        return $this->hasOldInput($key) ? $_SESSION['_old'][$key] : $default;
    }

    private function clearOldInputs(array $keys): void
    {
        if (!isset($_SESSION['_old'])) {
            return;
        }

        foreach ($keys as $key) {
            unset($_SESSION['_old'][$key]);
        }

        if ($_SESSION['_old'] === []) {
            unset($_SESSION['_old']);
        }
    }

    private function deriveInternalLocation(string $province, string $city): array
    {
        $provinceKey = trim($province);
        $cityKey = trim($city);

        if (!$this->isValidIranLocation($provinceKey, $cityKey)) {
            return [
                'country_code' => 'IR',
                'region_code' => '',
                'location_cell_l5' => '',
                'location_cell_l4' => '',
            ];
        }

        return [
            'country_code' => 'IR',
            'region_code' => $provinceKey,
            'location_cell_l5' => $cityKey,
            'location_cell_l4' => $provinceKey,
        ];
    }

    private function isValidIranLocation(string $province, string $city): bool
    {
        return isset(self::IRAN_LOCATIONS[$province], self::IRAN_LOCATIONS[$province]['cities'][$city]);
    }

    private function logException(Throwable $e, string $context): void
    {
        error_log(sprintf('[%s] %s: %s in %s:%d', $context, $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    }
}
