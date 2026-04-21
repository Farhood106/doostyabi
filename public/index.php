<?php

declare(strict_types=1);

$bootstrap = require __DIR__ . '/../bootstrap/app.php';

$bootstrap['router']->dispatch($bootstrap['request'], $bootstrap['app']);
