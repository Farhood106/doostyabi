#!/usr/bin/env php
<?php

declare(strict_types=1);

$seed = file_get_contents(__DIR__ . '/../database/i18n_seed_main_flow.sql');
if ($seed === false) {
    throw new RuntimeException('cannot_read_seed');
}

preg_match_all("/\('onboarding','([^']+)'/", $seed, $km);
$keys = array_fill_keys($km[1], true);

preg_match_all("/\((\d+),'([^']+)','([^']+)',NULL,'select','string','(\[[^']*\])'/", $seed, $dm, PREG_SET_ORDER);
$missing = [];
foreach ($dm as $row) {
    $prefKey = (string)$row[2];
    $allowed = json_decode((string)$row[4], true);
    if (!is_array($allowed)) {
        continue;
    }
    foreach ($allowed as $value) {
        $k = 'onboarding.goal_pref.option.' . $prefKey . '.' . (string)$value;
        if (!isset($keys[$k])) {
            $missing[] = $k;
        }
    }
}

$missing = array_values(array_unique($missing));
if ($missing !== []) {
    throw new RuntimeException('missing_goal_option_i18n_keys: ' . implode(', ', $missing));
}

echo "OK: goal question option i18n coverage verification passed\n";
