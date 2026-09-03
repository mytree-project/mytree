<?php

declare(strict_types=1);

namespace App\Application\Settings\Application;

interface ApplicationSettingsProvider
{
    public function current(): ApplicationSettings;
}
