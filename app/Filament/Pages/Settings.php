<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Settings\Application\ApplicationSettings;
use App\Application\Settings\Application\ApplicationSettingsProvider;
use App\Application\Settings\Application\UpdateApplicationSettings;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
final class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(ApplicationSettingsProvider::class)->current();

        $this->form->fill([
            'default_locale' => $settings->defaultLocale,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('default_locale')
                    ->label('Default locale')
                    ->helperText('Default application locale, for example en or pl-PL.')
                    ->required()
                    ->maxLength(35)
                    ->rules([
                        'regex:/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/',
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();
        $defaultLocale = $data['default_locale'] ?? null;

        if (! is_string($defaultLocale)) {
            throw new \UnexpectedValueException('Default locale form state must be a string.');
        }

        $actorId = auth()->id();

        app(UpdateApplicationSettings::class)->handle(
            settings: new ApplicationSettings(
                defaultLocale: $defaultLocale,
            ),
            changedBy: $actorId === null ? null : (string) $actorId,
        );

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
