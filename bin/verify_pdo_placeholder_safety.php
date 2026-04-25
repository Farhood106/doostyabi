#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    $root . '/src/Repositories',
];

$phpFiles = [];
foreach ($targets as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $phpFiles[] = $file->getPathname();
    }
}

sort($phpFiles);

$issues = [];

foreach ($phpFiles as $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read: {$path}\n");
        exit(1);
    }

    if (!preg_match_all('/([\"\'])(?:(?=(\\?))\\2.)*?\\1/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches[0] as [$literal, $offset]) {
        $body = substr($literal, 1, -1);
        $bodyLower = strtolower($body);
        if (
            str_contains($bodyLower, 'select ') === false
            && str_contains($bodyLower, 'insert ') === false
            && str_contains($bodyLower, 'update ') === false
            && str_contains($bodyLower, 'delete ') === false
        ) {
            continue;
        }

        if (!preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $body, $placeholders)) {
            continue;
        }

        $counts = array_count_values($placeholders[0]);
        $duplicates = array_keys(array_filter($counts, static fn(int $count): bool => $count > 1));

        if ($duplicates === []) {
            continue;
        }

        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
        $issues[] = [
            'file' => $path,
            'line' => $line,
            'duplicates' => $duplicates,
        ];
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Found repeated named placeholders in SQL literals:\n");
    foreach ($issues as $issue) {
        $relativePath = ltrim(str_replace($root, '', $issue['file']), '/');
        fwrite(
            STDERR,
            sprintf(" - %s:%d => %s\n", $relativePath, $issue['line'], implode(', ', $issue['duplicates']))
        );
    }
    exit(1);
}

echo "verify_pdo_placeholder_safety: all repository SQL literals avoid repeated named placeholders.\n";
