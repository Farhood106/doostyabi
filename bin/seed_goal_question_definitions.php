#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Services\GoalQuestionCatalogService;

require __DIR__ . '/../bootstrap/autoload.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = (new Connection($config))->pdo();
$catalog = new GoalQuestionCatalogService();

$goals = $pdo->query("SELECT id, slug FROM goals WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$upsertDef = $pdo->prepare(
    "INSERT INTO goal_preference_definitions
    (goal_id,pref_key,label_key,helper_text_key,input_type,value_type,allowed_values_json,weight,is_required,is_active,created_at,updated_at)
    VALUES (:goal_id,:pref_key,:label_key,:helper_text_key,:input_type,:value_type,:allowed_values_json,:weight,:is_required,1,NOW(),NOW())
    ON DUPLICATE KEY UPDATE label_key=VALUES(label_key),helper_text_key=VALUES(helper_text_key),input_type=VALUES(input_type),value_type=VALUES(value_type),allowed_values_json=VALUES(allowed_values_json),is_required=VALUES(is_required),is_active=1,updated_at=NOW()"
);
$upsertI18n = $pdo->prepare(
    "INSERT INTO i18n_texts (namespace,text_key,locale_code,text_value,created_at,updated_at)
     VALUES ('onboarding',:text_key,:locale,:text_value,NOW(),NOW())
     ON DUPLICATE KEY UPDATE text_value=VALUES(text_value),updated_at=NOW()"
);

foreach ($goals as $g) {
    $defs = $catalog->fallbackDefinitionsForGoalSlug((string)$g['slug'], (int)$g['id']);
    foreach ($defs as $d) {
        $upsertDef->execute([
            'goal_id' => (int)$d['goal_id'],
            'pref_key' => (string)$d['pref_key'],
            'label_key' => (string)$d['label_key'],
            'helper_text_key' => $d['helper_text_key'],
            'input_type' => (string)$d['input_type'],
            'value_type' => (string)$d['value_type'],
            'allowed_values_json' => json_encode((array)$d['allowed_values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'weight' => 1,
            'is_required' => (int)$d['is_required'],
        ]);

        $upsertI18n->execute(['text_key' => (string)$d['label_key'], 'locale' => 'fa', 'text_value' => str_replace('_', ' ', basename(str_replace('.', '/', (string)$d['pref_key'])))]);
        $upsertI18n->execute(['text_key' => (string)$d['label_key'], 'locale' => 'en', 'text_value' => str_replace('_', ' ', (string)$d['pref_key'])]);

        foreach ((array)$d['allowed_values'] as $option) {
            $opt = (string)$option;
            $key = 'onboarding.goal_pref.option.' . (string)$d['pref_key'] . '.' . $opt;
            $upsertI18n->execute(['text_key' => $key, 'locale' => 'fa', 'text_value' => $catalog->fallbackFaOptionLabel((string)$d['pref_key'], $opt)]);
            $upsertI18n->execute(['text_key' => $key, 'locale' => 'en', 'text_value' => str_replace('_', ' ', $opt)]);
        }
    }
}

echo "OK: seeded goal question definitions for active goals\n";
