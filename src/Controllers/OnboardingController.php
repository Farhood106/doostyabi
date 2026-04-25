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
use App\Services\OnboardingProgressService;
use App\Validation\Validator;
use Throwable;

final class OnboardingController
{
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
        $next = $this->app->make(OnboardingProgressService::class)->firstIncompleteStep($this->userId());
        if ($next === 'done') {
            Response::redirect('/dashboard');
        }
        Response::redirect('/onboarding/' . $next);
    }

    public function showBoundaries(Request $request): void
    {
        $this->guardStep('boundaries');
        View::render('onboarding/boundaries', $this->viewData());
    }

    public function saveBoundaries(Request $request): never
    {
        $this->guardStep('boundaries');
        $this->validateCsrf($request, '/onboarding/boundaries');

        $keys = (array)($request->input('boundary_key') ?? []);
        $values = (array)($request->input('boundary_value') ?? []);
        $importance = (array)($request->input('importance') ?? []);

        $rows = [];
        $errors = [];
        $allowedImportance = ['required', 'preferred', 'avoid'];

        foreach ($keys as $i => $key) {
            $k = trim((string)$key);
            $v = trim((string)($values[$i] ?? ''));
            $imp = (string)($importance[$i] ?? 'preferred');

            if ($k === '' && $v === '') {
                continue;
            }

            if (!in_array($imp, $allowedImportance, true)) {
                $errors['importance'][] = 'validation.invalid_importance';
            }

            if ($k === '' || $v === '') {
                $errors['boundary_key'][] = 'validation.required';
                continue;
            }

            $rows[] = ['key' => $k, 'value' => $v, 'importance' => $imp];
        }

        if ($rows === []) {
            $errors['boundary_key'][] = 'validation.required';
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
        Response::redirect('/onboarding/availability');
    }

    public function showAvailability(Request $request): void
    {
        $this->guardStep('availability');
        View::render('onboarding/availability', $this->viewData());
    }

    public function saveAvailability(Request $request): never
    {
        $this->guardStep('availability');
        $this->validateCsrf($request, '/onboarding/availability');

        $weekdays = (array)($request->input('weekday') ?? []);
        $starts = (array)($request->input('start_minute') ?? []);
        $ends = (array)($request->input('end_minute') ?? []);

        $rows = [];
        $errors = [];

        foreach ($weekdays as $i => $dayRaw) {
            $day = (int)$dayRaw;
            $start = (int)($starts[$i] ?? -1);
            $end = (int)($ends[$i] ?? -1);

            if ($day < 0 || $day > 6) {
                $errors['weekday'][] = 'validation.weekday';
            }
            if ($start < 0 || $start > 1439) {
                $errors['start_minute'][] = 'validation.time_range';
            }
            if ($end < 1 || $end > 1440) {
                $errors['end_minute'][] = 'validation.time_range';
            }
            if ($start >= $end) {
                $errors['end_minute'][] = 'validation.time_order';
            }

            $rows[] = [
                'weekday' => $day,
                'start_minute' => $start,
                'end_minute' => $end,
                'timezone_name' => 'Asia/Tehran',
            ];
        }

        if ($rows === []) {
            $errors['weekday'][] = 'validation.required';
        }

        if ($errors !== []) {
            flash('errors', $errors);
            Response::redirect('/onboarding/availability');
        }

        try {
            $this->app->make(OnboardingRepository::class)->replaceAvailability($this->userId(), $rows);
        } catch (Throwable $e) {
            $this->logException($e, 'onboarding.availability');
            flash('message', 'common.unexpected_error');
            Response::redirect('/onboarding/availability');
        }

        flash('message', 'onboarding.availability_saved');
        Response::redirect('/onboarding/goals');
    }

    public function showGoals(Request $request): void
    {
        $this->guardStep('goals');
        $goals = $this->app->make(GoalRepository::class)->activeGoals();
        View::render('onboarding/goals', $this->viewData() + ['goals' => $goals]);
    }

    public function saveGoals(Request $request): never
    {
        $this->guardStep('goals');
        $this->validateCsrf($request, '/onboarding/goals');
        $goalIds = array_values(array_unique(array_map('intval', (array)$request->input('goal_ids', []))));

        if (count($goalIds) === 0) {
            flash('errors', ['goal_ids' => ['validation.required']]);
            Response::redirect('/onboarding/goals');
        }

        $repo = $this->app->make(OnboardingRepository::class);
        $activeGoalIds = $repo->activeGoalIds($goalIds);

        if (count($activeGoalIds) !== count($goalIds)) {
            flash('errors', ['goal_ids' => ['validation.goals_invalid']]);
            Response::redirect('/onboarding/goals');
        }

        try {
            $uid = $this->userId();
            $repo->replaceGoals($uid, $goalIds);
            $repo->markProfileCompleted($uid);
        } catch (Throwable $e) {
            $this->logException($e, 'onboarding.goals');
            flash('message', 'common.unexpected_error');
            Response::redirect('/onboarding/goals');
        }

        unset($_SESSION['_old']);
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
        $locale = currentLocale();
        $selectedProvince = (string)old('province', 'tehran');
        if (!isset(self::IRAN_LOCATIONS[$selectedProvince])) {
            $selectedProvince = 'tehran';
        }

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
        ];
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
