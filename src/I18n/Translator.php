<?php

declare(strict_types=1);

namespace App\I18n;

use PDO;

final class Translator
{
    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $fallbackLocale,
    ) {}

    public function get(string $locale, string $key, array $replace = []): string
    {
        $line = $this->lines($locale)[$key] ?? $this->lines($this->fallbackLocale)[$key] ?? $key;

        foreach ($replace as $name => $value) {
            $line = str_replace(':' . $name, (string)$value, $line);
        }

        return $line;
    }

    private function lines(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $stmt = $this->pdo->prepare('SELECT text_key, text_value FROM i18n_texts WHERE locale_code = :locale');
        $stmt->execute(['locale' => $locale]);

        $rows = $stmt->fetchAll();
        self::$cache[$locale] = [];

        foreach ($rows as $row) {
            self::$cache[$locale][$row['text_key']] = $row['text_value'];
        }

        return self::$cache[$locale];
    }
}
