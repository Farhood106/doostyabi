<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\GoalQuestionCatalogService;
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
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM profile_boundaries WHERE user_id = :uid')->execute(['uid' => $userId]);

            $stmt = $this->pdo->prepare('INSERT INTO profile_boundaries (user_id, boundary_key, boundary_value, importance, created_at, updated_at) VALUES (:uid, :k, :v, :i, NOW(), NOW())');
            foreach ($rows as $row) {
                $stmt->execute(['uid' => $userId, 'k' => $row['key'], 'v' => $row['value'], 'i' => $row['importance']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
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

    public function replaceGoals(int $userId, array $goalIds, ?int $primaryGoalId = null): void
    {
        $this->pdo->prepare('DELETE FROM user_goals WHERE user_id = :uid')->execute(['uid' => $userId]);
        $stmt = $this->pdo->prepare('INSERT INTO user_goals (user_id, goal_id, priority, status, created_at, updated_at) VALUES (:uid, :goal, :priority, :status, NOW(), NOW())');

        $orderedGoalIds = array_values(array_unique(array_map('intval', $goalIds)));
        if ($primaryGoalId !== null && in_array($primaryGoalId, $orderedGoalIds, true)) {
            $orderedGoalIds = array_values(array_filter($orderedGoalIds, static fn(int $id): bool => $id !== $primaryGoalId));
            array_unshift($orderedGoalIds, $primaryGoalId);
        }

        $priority = 1;
        foreach ($orderedGoalIds as $goalId) {
            $stmt->execute(['uid' => $userId, 'goal' => (int)$goalId, 'priority' => $priority++, 'status' => 'active']);
        }
    }

    public function getPrimaryGoalIdForUser(int $userId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT goal_id FROM user_goals WHERE user_id = :uid AND status = 'active' ORDER BY priority ASC LIMIT 1"
        );
        $stmt->execute(['uid' => $userId]);
        $goalId = $stmt->fetchColumn();

        return $goalId === false ? null : (int)$goalId;
    }

    public function activeGoalIds(array $goalIds): array
    {
        if ($goalIds === []) return [];

        $placeholders = implode(',', array_fill(0, count($goalIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM goals WHERE is_active = 1 AND id IN ($placeholders)");
        $stmt->execute($goalIds);

        return array_map(static fn(array $row) => (int)$row['id'], $stmt->fetchAll());
    }

    public function getProfileForUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT birth_year, age_min_pref, age_max_pref, gender_identity, interested_in_gender, about_me, looking_for, social_energy, communication_style, emotional_openness, relationship_pace, independence_level, boundary_sensitivity, structure_vs_spontaneity, smoking_preference, drinking_preference, activity_level, region_code, location_cell_l5, distance_radius_km, profile_completed_at FROM profiles WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function getBoundariesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT boundary_key, boundary_value, importance FROM profile_boundaries WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAvailabilityForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT weekday, start_minute, end_minute, timezone_name FROM availability_slots WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getActiveGoalIdsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT goal_id FROM user_goals WHERE user_id = :uid AND status = 'active' ORDER BY priority ASC");
        $stmt->execute(['uid' => $userId]);
        return array_map(static fn(array $row): int => (int)$row['goal_id'], $stmt->fetchAll());
    }

    public function completionState(int $userId): array
    {
        $profile = $this->hasRows('profiles', $userId);
        $boundaries = $this->hasRows('profile_boundaries', $userId);
        $availability = $this->hasRows('availability_slots', $userId);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) c FROM user_goals WHERE user_id = :uid AND status='active'");
        $stmt->execute(['uid' => $userId]);
        $goals = (int)$stmt->fetch()['c'] > 0;
        $goal_questions = $this->goalQuestionsCompleted($userId);

        return compact('profile', 'boundaries', 'availability', 'goals', 'goal_questions');
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

    public function goalPreferenceDefinitions(array $goalIds): array
    {
        if ($goalIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($goalIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, goal_id, pref_key, label_key, helper_text_key, input_type, value_type, allowed_values_json, is_required
             FROM goal_preference_definitions
             WHERE is_active = 1
               AND goal_id IN ($placeholders)
             ORDER BY goal_id ASC, id ASC"
        );
        $stmt->execute($goalIds);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['allowed_values'] = json_decode((string)($row['allowed_values_json'] ?? '[]'), true) ?? [];
        }

        $rowsByGoal = [];
        foreach ($rows as $row) {
            $rowsByGoal[(int)$row['goal_id']][] = $row;
        }

        foreach ($goalIds as $goalIdRaw) {
            $goalId = (int)$goalIdRaw;
            if (($rowsByGoal[$goalId] ?? []) !== []) {
                continue;
            }
            $slug = $this->goalSlugById($goalId);
            if ($slug === '') {
                continue;
            }
            $fallback = (new GoalQuestionCatalogService())->fallbackDefinitionsForGoalSlug($slug, $goalId);
            if ($fallback === []) {
                continue;
            }
            error_log(sprintf('[onboarding.goal_questions] using fallback in-code definitions for goal_id=%d slug=%s', $goalId, $slug));
            $rowsByGoal[$goalId] = $fallback;
        }

        $merged = [];
        foreach ($goalIds as $goalIdRaw) {
            $goalId = (int)$goalIdRaw;
            foreach (($rowsByGoal[$goalId] ?? []) as $row) {
                $merged[] = $row;
            }
        }

        if ($merged !== []) {
            return $merged;
        }

        return $rows;
    }

    public function replaceGoalPreferenceValues(int $userId, array $valuesByGoalAndPref): void
    {
        $userGoalStmt = $this->pdo->prepare('SELECT id, goal_id FROM user_goals WHERE user_id = :uid AND status = :status');
        $userGoalStmt->execute(['uid' => $userId, 'status' => 'active']);
        $goalToUserGoalId = [];
        foreach ($userGoalStmt->fetchAll() as $row) {
            $goalToUserGoalId[(int)$row['goal_id']] = (int)$row['id'];
        }

        if ($goalToUserGoalId === []) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            if ($this->isSqlite()) {
                $delete = $this->pdo->prepare(
                    'DELETE FROM user_goal_preferences
                     WHERE user_goal_id IN (SELECT id FROM user_goals WHERE user_id = :uid)'
                );
            } else {
                $delete = $this->pdo->prepare(
                    'DELETE ugp FROM user_goal_preferences ugp
                     JOIN user_goals ug ON ug.id = ugp.user_goal_id
                     WHERE ug.user_id = :uid'
                );
            }
            $delete->execute(['uid' => $userId]);

            $defStmt = $this->pdo->prepare(
                'SELECT id FROM goal_preference_definitions
                 WHERE goal_id = :goal_id
                   AND pref_key = :pref_key
                   AND is_active = 1
                 LIMIT 1'
            );
            $insert = $this->pdo->prepare(
                'INSERT INTO user_goal_preferences (user_goal_id, preference_def_id, value_string, value_number, value_bool, value_json, created_at, updated_at)
                 VALUES (:user_goal_id, :def_id, :value_string, NULL, NULL, :value_json, :created_at, :updated_at)'
            );
            $now = date('Y-m-d H:i:s');

            foreach ($valuesByGoalAndPref as $goalIdRaw => $prefValues) {
                $goalId = (int)$goalIdRaw;
                if (!isset($goalToUserGoalId[$goalId]) || !is_array($prefValues)) {
                    continue;
                }
                foreach ($prefValues as $prefKey => $value) {
                    $prefKey = trim((string)$prefKey);
                    if ($prefKey === '') {
                        continue;
                    }

                    $valueString = null;
                    $valueJson = null;
                    if (is_array($value)) {
                        $normalized = array_values(array_filter(array_map(static fn(mixed $v): string => trim((string)$v), $value), static fn(string $v): bool => $v !== ''));
                        if ($normalized === []) {
                            continue;
                        }
                        $valueJson = json_encode($normalized, JSON_UNESCAPED_UNICODE);
                    } else {
                        $valueString = trim((string)$value);
                        if ($valueString === '') {
                            continue;
                        }
                    }

                    $defStmt->execute(['goal_id' => $goalId, 'pref_key' => $prefKey]);
                    $defId = $defStmt->fetchColumn();
                    if ($defId === false) {
                        $defId = $this->ensureFallbackDefinition($goalId, $prefKey);
                    }
                    if ($defId === false) {
                        continue;
                    }
                    $insert->execute([
                        'user_goal_id' => $goalToUserGoalId[$goalId],
                        'def_id' => (int)$defId,
                        'value_string' => $valueString,
                        'value_json' => $valueJson,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function ensureFallbackDefinition(int $goalId, string $prefKey): int|false
    {
        $slug = $this->goalSlugById($goalId);
        if ($slug === '') {
            return false;
        }
        $fallbackDefs = (new GoalQuestionCatalogService())->fallbackDefinitionsForGoalSlug($slug, $goalId);
        foreach ($fallbackDefs as $def) {
            if ((string)($def['pref_key'] ?? '') !== $prefKey) {
                continue;
            }
            $allowedJson = json_encode((array)($def['allowed_values'] ?? []), JSON_UNESCAPED_UNICODE);
            $insert = $this->pdo->prepare(
                'INSERT INTO goal_preference_definitions (goal_id, pref_key, label_key, helper_text_key, input_type, value_type, allowed_values_json, is_required, is_active, weight, created_at, updated_at)
                 VALUES (:goal_id, :pref_key, :label_key, :helper_text_key, :input_type, :value_type, :allowed_values_json, :is_required, 1, :weight, :created_at, :updated_at)'
            );
            $now = date('Y-m-d H:i:s');
            $insert->execute([
                'goal_id' => $goalId,
                'pref_key' => $prefKey,
                'label_key' => (string)($def['label_key'] ?? ('onboarding.goal_pref.' . $prefKey)),
                'helper_text_key' => (string)($def['helper_text_key'] ?? ''),
                'input_type' => (string)($def['input_type'] ?? 'select'),
                'value_type' => (string)($def['value_type'] ?? 'string'),
                'allowed_values_json' => $allowedJson ?: '[]',
                'is_required' => (int)($def['is_required'] ?? 0),
                'weight' => (int)($def['weight'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return (int)$this->pdo->lastInsertId();
        }
        return false;
    }

    public function getGoalPreferenceValuesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ug.goal_id, gpd.pref_key, ugp.value_string, ugp.value_json
             FROM user_goal_preferences ugp
             JOIN user_goals ug ON ug.id = ugp.user_goal_id
             JOIN goal_preference_definitions gpd ON gpd.id = ugp.preference_def_id
             WHERE ug.user_id = :uid'
        );
        $stmt->execute(['uid' => $userId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $goalId = (int)$row['goal_id'];
            $prefKey = (string)$row['pref_key'];
            $json = (string)($row['value_json'] ?? '');
            if ($json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $result[$goalId][$prefKey] = array_values(array_map(static fn(mixed $v): string => (string)$v, $decoded));
                    continue;
                }
            }
            $result[$goalId][$prefKey] = (string)($row['value_string'] ?? '');
        }

        return $result;
    }

    public function goalQuestionsCompleted(int $userId): bool
    {
        $goalIds = $this->getActiveGoalIdsForUser($userId);
        if ($goalIds === []) {
            return false;
        }

        $primaryGoalId = (int)$goalIds[0];
        $catalog = new GoalQuestionCatalogService();
        $primarySlug = $this->goalSlugById($primaryGoalId);
        $orderedKeys = $catalog->orderedKeysForPrimaryGoalSlug($primarySlug);
        $defs = $this->goalPreferenceDefinitions([$primaryGoalId]);
        if ($orderedKeys !== []) {
            $allowedKeyMap = array_fill_keys($orderedKeys, true);
            $defs = array_values(array_filter($defs, static fn(array $d): bool => isset($allowedKeyMap[(string)($d['pref_key'] ?? '')])));
        }
        $requiredDefs = array_values(array_filter($defs, static fn(array $d): bool => (int)($d['is_required'] ?? 0) === 1));
        if ($requiredDefs === []) {
            return true;
        }

        $saved = $this->getGoalPreferenceValuesForUser($userId);
        foreach ($requiredDefs as $def) {
            $goalId = (int)$def['goal_id'];
            $prefKey = (string)$def['pref_key'];
            $raw = $saved[$goalId][$prefKey] ?? '';
            if (is_array($raw)) {
                if ($raw === []) {
                    return false;
                }
                continue;
            }
            $value = trim((string)$raw);
            if ($value === '') {
                return false;
            }
            if ($catalog->isSensitiveGoalSlug($primarySlug) && $catalog->isStrictSensitiveKey($prefKey) && in_array(strtolower($value), ['not_sure', 'unsure'], true)) {
                return false;
            }
        }

        return true;
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function goalSlugById(int $goalId): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT slug FROM goals WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $goalId]);
            $slug = $stmt->fetchColumn();
            return $slug === false ? '' : trim((string)$slug);
        } catch (\Throwable) {
            return '';
        }
    }
}
