<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Filament\Support\SourceWorkspacePage;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Primary Source Acquisition page.
 *
 * SourceWorkspacePage keeps Filament behind the application boundaries and
 * persists through SaveSourceDraft / StageSourceAsset rather than Eloquent.
 */
final class SourceEditor extends SourceWorkspacePage
{
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function save(): void
    {
        $this->resetErrorBag();

        try {
            parent::save();
        } catch (ValidationException $exception) {
            $this->reportValidationFailure($exception);
        } catch (Throwable $exception) {
            report($exception);

            $message = __('ui.workspace.unexpected_save_failure');
            $this->addError('data', $message);

            Notification::make()
                ->title(__('ui.workspace.save_failed'))
                ->body($message)
                ->danger()
                ->send();
        }
    }

    private function reportValidationFailure(ValidationException $exception): void
    {
        $firstMessage = __('ui.workspace.validation_failed');
        $hasMessage = false;

        foreach ($exception->errors() as $path => $messages) {
            $mappedPath = $this->workspaceValidationPath($path);

            foreach ($messages as $message) {
                $this->addError($mappedPath, $message);

                if (! $hasMessage) {
                    $firstMessage = $message;
                    $hasMessage = true;
                }
            }
        }

        Notification::make()
            ->title(__('ui.workspace.save_failed'))
            ->body($firstMessage)
            ->danger()
            ->send();
    }

    private function workspaceValidationPath(string $path): string
    {
        foreach ([
            'data.mentions' => 'evidenceData.mentions',
            'data.fields' => 'evidenceData.fields',
            'data.event_contexts' => 'evidenceData.event_contexts',
        ] as $sourcePath => $workspacePath) {
            if ($path === $sourcePath) {
                return $workspacePath;
            }

            if (str_starts_with($path, $sourcePath . '.')) {
                return $workspacePath . substr($path, strlen($sourcePath));
            }
        }

        return $path;
    }
}
