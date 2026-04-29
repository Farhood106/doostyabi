<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\AdminGoalQuestionRepository;
use App\Security\Csrf;

final class AdminController
{
    public function __construct(private readonly App $app) {}

    private function guardAdmin(): void
    {
        $uid = (int)($this->app->make(AuthService::class)->userId() ?? 0);
        $adminIds = array_map('intval', explode(',', (string)($this->app->config('app.admin_user_ids', '1'))));
        if (!in_array($uid, $adminIds, true)) {
            flash('message', 'common.unexpected_error');
            Response::redirect('/dashboard');
        }
    }

    public function index(Request $request): void
    {
        $this->guardAdmin();
        View::render('admin/index', ['errors' => flashGet('errors', []), 'message' => flashGet('message')]);
    }

    public function goals(Request $request): void
    {
        $this->guardAdmin();
        $repo = $this->app->make(AdminGoalQuestionRepository::class);
        View::render('admin/goals', ['goals' => $repo->goals(), 'errors' => flashGet('errors', []), 'message' => flashGet('message')]);
    }

    public function goalQuestions(Request $request): void
    {
        $this->guardAdmin();
        $repo = $this->app->make(AdminGoalQuestionRepository::class);
        $goalId = (int)$request->input('goal_id', 0);
        $goals = $repo->goals();
        if ($goalId <= 0 && $goals !== []) { $goalId = (int)$goals[0]['id']; }
        View::render('admin/goal_questions', ['goals' => $goals, 'goalId' => $goalId, 'definitions' => $goalId > 0 ? $repo->definitionsByGoalId($goalId) : [], 'errors' => flashGet('errors', []), 'message' => flashGet('message')]);
    }

    public function saveGoalQuestion(Request $request): never
    {
        $this->guardAdmin();
        $csrf = $this->app->make(Csrf::class);
        if (!$csrf->validate((string)$request->input('_token'))) { Response::redirect('/admin/goal-questions'); }
        $data = [
            'id' => (int)$request->input('id', 0),
            'goal_id' => (int)$request->input('goal_id', 0),
            'pref_key' => trim((string)$request->input('pref_key', '')),
            'label_key' => trim((string)$request->input('label_key', '')),
            'helper_text_key' => trim((string)$request->input('helper_text_key', '')) ?: null,
            'input_type' => trim((string)$request->input('input_type', 'select')),
            'value_type' => trim((string)$request->input('value_type', 'string')),
            'allowed_values_json' => trim((string)$request->input('allowed_values_json', '[]')),
            'is_required' => (int)$request->input('is_required', 0),
            'weight' => (float)$request->input('weight', 1),
            'is_active' => (int)$request->input('is_active', 1),
        ];
        $this->app->make(AdminGoalQuestionRepository::class)->upsertDefinition($data);
        flash('message', 'onboarding.goals_saved');
        Response::redirect('/admin/goal-questions?goal_id=' . $data['goal_id']);
    }

    public function toggleGoalQuestion(Request $request): never
    {
        $this->guardAdmin();
        $csrf = $this->app->make(Csrf::class);
        if (!$csrf->validate((string)$request->input('_token'))) { Response::redirect('/admin/goal-questions'); }
        $id = (int)$request->input('id', 0);
        $goalId = (int)$request->input('goal_id', 0);
        $isActive = (int)$request->input('is_active', 0);
        $this->app->make(AdminGoalQuestionRepository::class)->toggleDefinition($id, $isActive);
        Response::redirect('/admin/goal-questions?goal_id=' . $goalId);
    }
}
