<?php

declare(strict_types=1);

namespace App\Application\Settings\Application;

use InvalidArgumentException;

final readonly class ApplicationSettings
{
    public function __construct(
        public string $defaultLocale,
    ) {
        if (
            strlen($defaultLocale) > 35
            || preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D', $defaultLocale) !== 1
        ) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a supported locale identifier.',
                $defaultLocale,
            ));
        }
    }
}
