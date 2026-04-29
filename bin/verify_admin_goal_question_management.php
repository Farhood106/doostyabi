#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Repositories\AdminGoalQuestionRepository;
use App\Repositories\OnboardingRepository;

require __DIR__ . '/../bootstrap/autoload.php';

function aassert(bool $ok, string $msg): void { if (!$ok) throw new RuntimeException('Assertion failed: '.$msg); }

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));

$pdo->exec('CREATE TABLE goals (id INTEGER PRIMARY KEY, slug TEXT, title_key TEXT, description_key TEXT, is_active INTEGER, sort_order INTEGER)');
$pdo->exec('CREATE TABLE goal_preference_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, goal_id INTEGER, pref_key TEXT, label_key TEXT, helper_text_key TEXT, input_type TEXT, value_type TEXT, allowed_values_json TEXT, min_value REAL, max_value REAL, weight REAL, is_required INTEGER, is_active INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(goal_id,pref_key))');
$pdo->exec('CREATE TABLE i18n_texts (id INTEGER PRIMARY KEY AUTOINCREMENT, namespace TEXT, text_key TEXT, locale_code TEXT, text_value TEXT, created_at TEXT, updated_at TEXT, UNIQUE(namespace,text_key,locale_code))');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE user_goals (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,goal_id INTEGER,priority INTEGER,status TEXT,created_at TEXT,updated_at TEXT)');
$pdo->exec('CREATE TABLE user_goal_preferences (id INTEGER PRIMARY KEY AUTOINCREMENT,user_goal_id INTEGER,preference_def_id INTEGER,value_string TEXT,value_number REAL,value_bool INTEGER,value_json TEXT,created_at TEXT,updated_at TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE profile_boundaries (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER)');
$pdo->exec('CREATE TABLE availability_slots (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER)');

$pdo->exec("INSERT INTO goals (id,slug,title_key,description_key,is_active,sort_order) VALUES (1,'emotional_connection','a','b',1,1),(2,'travel_companion','a2','b2',1,2)");

$adminRepo = new AdminGoalQuestionRepository($pdo);
$onboardingRepo = new OnboardingRepository($pdo);

$allGoals = $adminRepo->goals();
aassert(count($allGoals) === 2, 'admin goal list should load');

$defs1 = $onboardingRepo->goalPreferenceDefinitions([1]);
aassert(count($defs1) >= 4, 'fallback should provide definitions for active goal with no DB rows');

$adminRepo->upsertDefinition([
  'goal_id' => 1,
  'pref_key' => 'seek.emotional_climate',
  'label_key' => 'x.label',
  'helper_text_key' => 'x.help',
  'input_type' => 'select',
  'value_type' => 'string',
  'allowed_values_json' => '["calm","warm"]',
  'is_required' => 1,
  'weight' => 2,
  'is_active' => 1,
]);
$defsAdmin = $adminRepo->definitionsByGoalId(1);
aassert(count($defsAdmin) >= 1, 'admin save should insert definition');
$id = (int)$defsAdmin[0]['id'];
$adminRepo->toggleDefinition($id, 0);
$defsToggled = $adminRepo->definitionsByGoalId(1);
aassert((int)$defsToggled[0]['is_active'] === 0, 'toggle should disable definition');

echo "OK: admin goal question management verification passed\n";
