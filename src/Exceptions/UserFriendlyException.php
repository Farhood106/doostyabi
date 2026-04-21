<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class UserFriendlyException extends RuntimeException
{
    public function __construct(public readonly string $translationKey)
    {
        parent::__construct($translationKey);
    }
}
