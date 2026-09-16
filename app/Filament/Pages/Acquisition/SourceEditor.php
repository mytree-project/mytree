<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Filament\Support\SourceWorkspacePage;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
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

    public function evidenceForm(Schema $schema): Schema
    {
        $schema = parent::evidenceForm($schema);

        foreach ($schema->getComponents(withHidden: true) as $component) {
            if ($component instanceof Repeater && $component->getName() === 'mentions') {
                $component->itemNumbers();
            }
        }

        return $schema;
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
                $message = $this->workspaceValidationMessage($path, $message);
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

            if (str_starts_with($path, $sourcePath.'.')) {
                return $workspacePath.substr($path, strlen($sourcePath));
            }
        }

        return $path;
    }

    private function workspaceValidationMessage(string $path, string $message): string
    {
        if (preg_match('/^data\.mentions\.(\d+)\.raw_data_json$/', $path, $matches) !== 1) {
            return $message;
        }

        $mentionIndex = (int) $matches[1];
        $mention = $this->mentionAtPresentationIndex($mentionIndex);
        if ($mention === null) {
            return $message;
        }

        $rawData = $mention['raw_data_json'] ?? null;
        if (! is_string($rawData) || trim($rawData) === '' || json_validate($rawData)) {
            return $message;
        }

        $localKey = $mention['local_key'] ?? null;
        $localKey = is_string($localKey) && trim($localKey) !== '' ? trim($localKey) : null;

        return __('ui.workspace.mention_json_syntax', [
            'number' => $mentionIndex + 1,
            'key_suffix' => $localKey === null ? '' : " ($localKey)",
        ]);
    }

    /** @return array<string, mixed>|null */
    private function mentionAtPresentationIndex(int $index): ?array
    {
        $evidenceData = is_array($this->evidenceData) ? $this->evidenceData : [];
        $mentions = $evidenceData['mentions'] ?? [];
        if (! is_array($mentions)) {
            return null;
        }

        $mention = array_values($mentions)[$index] ?? null;

        return is_array($mention) ? $mention : null;
    }
}
