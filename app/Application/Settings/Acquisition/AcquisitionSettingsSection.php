<?php

declare(strict_types=1);

namespace App\Application\Settings\Acquisition;

use App\Application\Settings\SettingsSection;

final class AcquisitionSettingsSection implements SettingsSection
{
    public const KEY = 'acquisition';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Acquisition';
    }

    public function definitions(): array
    {
        return [];
    }
}
