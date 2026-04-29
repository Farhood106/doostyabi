<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\ChatController;
use App\Controllers\DashboardController;
use App\Controllers\MatchInterestController;
use App\Controllers\NotificationController;
use App\Controllers\OnboardingController;
use App\Controllers\RevealController;

$router->get('/', [AuthController::class, 'showLogin'], [$guestMiddleware]);

$router->get('/register', [AuthController::class, 'showRegister'], [$guestMiddleware]);
$router->post('/register', [AuthController::class, 'register'], [$guestMiddleware]);
$router->get('/login', [AuthController::class, 'showLogin'], [$guestMiddleware]);
$router->post('/login', [AuthController::class, 'login'], [$guestMiddleware]);
$router->post('/logout', [AuthController::class, 'logout'], [$authMiddleware]);

$router->get('/dashboard', [DashboardController::class, 'index'], [$authMiddleware]);
$router->post('/notifications/read', [NotificationController::class, 'markRead'], [$authMiddleware]);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], [$authMiddleware]);
$router->post('/match-interest', [MatchInterestController::class, 'store'], [$authMiddleware]);
$router->get('/chat', [ChatController::class, 'show'], [$authMiddleware]);
$router->post('/chat/send', [ChatController::class, 'send'], [$authMiddleware]);
$router->get('/chat/poll', [ChatController::class, 'poll']);
$router->post('/reveal/request', [RevealController::class, 'create'], [$authMiddleware]);
$router->post('/reveal/respond', [RevealController::class, 'respond'], [$authMiddleware]);
$router->post('/reveal/cancel', [RevealController::class, 'cancel'], [$authMiddleware]);

$router->get('/onboarding/profile', [OnboardingController::class, 'showProfile'], [$authMiddleware]);
$router->post('/onboarding/profile', [OnboardingController::class, 'saveProfile'], [$authMiddleware]);

$router->get('/onboarding/boundaries', [OnboardingController::class, 'showBoundaries'], [$authMiddleware]);
$router->post('/onboarding/boundaries', [OnboardingController::class, 'saveBoundaries'], [$authMiddleware]);

$router->get('/onboarding/availability', [OnboardingController::class, 'showAvailability'], [$authMiddleware]);
$router->post('/onboarding/availability', [OnboardingController::class, 'saveAvailability'], [$authMiddleware]);

$router->get('/onboarding/goals', [OnboardingController::class, 'showGoals'], [$authMiddleware]);
$router->post('/onboarding/goals', [OnboardingController::class, 'saveGoals'], [$authMiddleware]);
$router->get('/onboarding/goal-questions', [OnboardingController::class, 'showGoalQuestions'], [$authMiddleware]);
$router->post('/onboarding/goal-questions', [OnboardingController::class, 'saveGoalQuestions'], [$authMiddleware]);

$router->get('/admin', [AdminController::class, 'index'], [$authMiddleware]);
$router->get('/admin/goals', [AdminController::class, 'goals'], [$authMiddleware]);
$router->get('/admin/goal-questions', [AdminController::class, 'goalQuestions'], [$authMiddleware]);
$router->post('/admin/goal-questions/save', [AdminController::class, 'saveGoalQuestion'], [$authMiddleware]);
$router->post('/admin/goal-questions/toggle', [AdminController::class, 'toggleGoalQuestion'], [$authMiddleware]);
