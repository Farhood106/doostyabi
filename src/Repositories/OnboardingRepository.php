<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OnboardingRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function saveProfile(int $userId, array $data): void
    {
        $sql = 'INSERT INTO profiles (user_id,birth_year,age_min_pref,age_max_pref,gender_identity,interested_in_gender,about_me,looking_for,social_energy,communication_style,emotional_openness,relationship_pace,independence_level,boundary_sensitivity,structure_vs_spontaneity,smoking_preference,drinking_preference,activity_level,country_code,region_code,location_cell_l5,location_cell_l4,distance_radius_km,profile_completed_at,created_at,updated_at)
                VALUES (:user_id,:birth_year,:age_min_pref,:age_max_pref,:gender_identity,:interested_in_gender,:about_me,:looking_for,:social_energy,:communication_style,:emotional_openness,:relationship_pace,:independence_level,:boundary_sensitivity,:structure_vs_spontaneity,:smoking_preference,:drinking_preference,:activity_level,:country_code,:region_code,:location_cell_l5,:location_cell_l4,:distance_radius_km,NULL,NOW(),NOW())
                ON DUPLICATE KEY UPDATE
                birth_year=VALUES(birth_year), age_min_pref=VALUES(age_min_pref), age_max_pref=VALUES(age_max_pref), gender_identity=VALUES(gender_identity), interested_in_gender=VALUES(interested_in_gender), about_me=VALUES(about_me), looking_for=VALUES(looking_for), social_energy=VALUES(social_energy), communication_style=VALUES(communication_style), emotional_openness=VALUES(emotional_openness), relationship_pace=VALUES(relationship_pace), independence_level=VALUES(independence_level), boundary_sensitivity=VALUES(boundary_sensitivity), structure_vs_spontaneity=VALUES(structure_vs_spontaneity), smoking_preference=VALUES(smoking_preference), drinking_preference=VALUES(drinking_preference), activity_level=VALUES(activity_level), country_code=VALUES(country_code), region_code=VALUES(region_code), location_cell_l5=VALUES(location_cell_l5), location_cell_l4=VALUES(location_cell_l4), distance_radius_km=VALUES(distance_radius_km), profile_completed_at=NULL, updated_at=NOW()';

        $stmt = $this->pdo->prepare($sql);
        $params = [
            'user_id' => $userId,
            'birth_year' => $data['birth_year'] ?? null,
            'age_min_pref' => $data['age_min_pref'] ?? null,
            'age_max_pref' => $data['age_max_pref'] ?? null,
            'gender_identity' => $data['gender_identity'] ?? null,
            'interested_in_gender' => $data['interested_in_gender'] ?? null,
            'about_me' => $data['about_me'] ?? null,
            'looking_for' => $data['looking_for'] ?? null,
            'social_energy' => $data['social_energy'] ?? null,
            'communication_style' => $data['communication_style'] ?? null,
            'emotional_openness' => $data['emotional_openness'] ?? null,
            'relationship_pace' => $data['relationship_pace'] ?? null,
            'independence_level' => $data['independence_level'] ?? null,
            'boundary_sensitivity' => $data['boundary_sensitivity'] ?? null,
            'structure_vs_spontaneity' => $data['structure_vs_spontaneity'] ?? null,
            'smoking_preference' => $data['smoking_preference'] ?? null,
            'drinking_preference' => $data['drinking_preference'] ?? null,
            'activity_level' => $data['activity_level'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'region_code' => $data['region_code'] ?? null,
            'location_cell_l5' => $data['location_cell_l5'] ?? null,
            'location_cell_l4' => $data['location_cell_l4'] ?? null,
            'distance_radius_km' => $data['distance_radius_km'] ?? null,
        ];
        $stmt->execute($params);
    }

    public function replaceBoundaries(int $userId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM profile_boundaries WHERE user_id = :uid')->execute(['uid' => $userId]);

        $stmt = $this->pdo->prepare('INSERT INTO profile_boundaries (user_id, boundary_key, boundary_value, importance, created_at, updated_at) VALUES (:uid, :k, :v, :i, NOW(), NOW())');
        foreach ($rows as $row) {
            $stmt->execute(['uid' => $userId, 'k' => $row['key'], 'v' => $row['value'], 'i' => $row['importance']]);
        }
    }

    public function replaceAvailability(int $userId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM availability_slots WHERE user_id = :uid')->execute(['uid' => $userId]);
        $stmt = $this->pdo->prepare('INSERT INTO availability_slots (user_id, weekday, start_minute, end_minute, timezone_name, created_at, updated_at) VALUES (:uid, :weekday, :start, :end, :tz, NOW(), NOW())');

        foreach ($rows as $row) {
            $stmt->execute([
                'uid' => $userId,
                'weekday' => $row['weekday'],
                'start' => $row['start_minute'],
                'end' => $row['end_minute'],
                'tz' => $row['timezone_name'],
            ]);
        }
    }

    public function replaceGoals(int $userId, array $goalIds): void
    {
        $this->pdo->prepare('DELETE FROM user_goals WHERE user_id = :uid')->execute(['uid' => $userId]);
        $stmt = $this->pdo->prepare('INSERT INTO user_goals (user_id, goal_id, priority, status, created_at, updated_at) VALUES (:uid, :goal, :priority, :status, NOW(), NOW())');

        $priority = 1;
        foreach ($goalIds as $goalId) {
            $stmt->execute(['uid' => $userId, 'goal' => (int)$goalId, 'priority' => $priority++, 'status' => 'active']);
        }
    }

    public function activeGoalIds(array $goalIds): array
    {
        if ($goalIds === []) return [];

        $placeholders = implode(',', array_fill(0, count($goalIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM goals WHERE is_active = 1 AND id IN ($placeholders)");
        $stmt->execute($goalIds);

        return array_map(static fn(array $row) => (int)$row['id'], $stmt->fetchAll());
    }

    public function completionState(int $userId): array
    {
        $profile = $this->hasRows('profiles', $userId);
        $boundaries = $this->hasRows('profile_boundaries', $userId);
        $availability = $this->hasRows('availability_slots', $userId);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) c FROM user_goals WHERE user_id = :uid AND status='active'");
        $stmt->execute(['uid' => $userId]);
        $goals = (int)$stmt->fetch()['c'] > 0;

        return compact('profile', 'boundaries', 'availability', 'goals');
    }

    private function hasRows(string $table, int $userId): bool
    {
        $allowed = ['profiles', 'profile_boundaries', 'availability_slots'];
        if (!in_array($table, $allowed, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) c FROM {$table} WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);

        return (int)$stmt->fetch()['c'] > 0;
    }

    public function markProfileCompleted(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE profiles SET profile_completed_at = NOW(), updated_at = NOW() WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
    }
}
