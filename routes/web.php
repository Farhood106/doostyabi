<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\DashboardController;
use App\Controllers\MatchInterestController;
use App\Controllers\OnboardingController;

$router->get('/', [AuthController::class, 'showLogin'], [$guestMiddleware]);

$router->get('/register', [AuthController::class, 'showRegister'], [$guestMiddleware]);
$router->post('/register', [AuthController::class, 'register'], [$guestMiddleware]);
$router->get('/login', [AuthController::class, 'showLogin'], [$guestMiddleware]);
$router->post('/login', [AuthController::class, 'login'], [$guestMiddleware]);
$router->post('/logout', [AuthController::class, 'logout'], [$authMiddleware]);

$router->get('/dashboard', [DashboardController::class, 'index'], [$authMiddleware]);
$router->post('/match-interest', [MatchInterestController::class, 'store'], [$authMiddleware]);
$router->get('/chat', [ChatController::class, 'show'], [$authMiddleware]);
$router->post('/chat/send', [ChatController::class, 'send'], [$authMiddleware]);
$router->get('/chat/poll', [ChatController::class, 'poll'], [$authMiddleware]);

$router->get('/onboarding/profile', [OnboardingController::class, 'showProfile'], [$authMiddleware]);
$router->post('/onboarding/profile', [OnboardingController::class, 'saveProfile'], [$authMiddleware]);

$router->get('/onboarding/boundaries', [OnboardingController::class, 'showBoundaries'], [$authMiddleware]);
$router->post('/onboarding/boundaries', [OnboardingController::class, 'saveBoundaries'], [$authMiddleware]);

$router->get('/onboarding/availability', [OnboardingController::class, 'showAvailability'], [$authMiddleware]);
$router->post('/onboarding/availability', [OnboardingController::class, 'saveAvailability'], [$authMiddleware]);

$router->get('/onboarding/goals', [OnboardingController::class, 'showGoals'], [$authMiddleware]);
$router->post('/onboarding/goals', [OnboardingController::class, 'saveGoals'], [$authMiddleware]);
