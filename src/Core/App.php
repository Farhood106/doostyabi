<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthService;
use App\Database\Connection;
use App\I18n\Translator;
use App\Repositories\GoalRepository;
use App\Repositories\AdminGoalQuestionRepository;
use App\Repositories\OnboardingRepository;
use App\Repositories\UserRepository;
use App\Security\Csrf;
use App\Services\OnboardingProgressService;
use PDO;

final class App
{
    private array $instances = [];

    public function __construct(
        private readonly array $config,
        public readonly Request $request,
    ) {}

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function make(string $class): mixed
    {
        return $this->instances[$class] ??= match ($class) {
            PDO::class => (new Connection($this->config('database')))->pdo(),
            Csrf::class => new Csrf(),
            Translator::class => new Translator($this->make(PDO::class), (string)$this->config('app.fallback_locale', 'en')),
            UserRepository::class => new UserRepository($this->make(PDO::class)),
            GoalRepository::class => new GoalRepository($this->make(PDO::class)),
            AdminGoalQuestionRepository::class => new AdminGoalQuestionRepository($this->make(PDO::class)),
            OnboardingRepository::class => new OnboardingRepository($this->make(PDO::class)),
            OnboardingProgressService::class => new OnboardingProgressService($this->make(OnboardingRepository::class)),
            AuthService::class => new AuthService($this->make(UserRepository::class)),
            default => new $class($this),
        };
    }
}
