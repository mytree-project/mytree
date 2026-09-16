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
    /** @var array<string, list<string|null>> */
    public array $workspaceValidationDetails = [];

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
        $this->workspaceValidationDetails = [];

        try {
            parent::save();
            $firstMessage = $this->normalizeReportedWorkspaceErrors();
            if ($firstMessage !== null) {
                $this->normalizeLatestErrorNotification($firstMessage);
            }
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
                $presentedMessage = $this->addWorkspaceValidationError($mappedPath, $message);

                if (! $hasMessage) {
                    $firstMessage = $presentedMessage;
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

    private function normalizeReportedWorkspaceErrors(): ?string
    {
        $messages = $this->getErrorBag()->getMessages();
        if ($messages === []) {
            return null;
        }

        $this->resetErrorBag();
        $firstMessage = null;

        foreach ($messages as $path => $pathMessages) {
            foreach ($pathMessages as $message) {
                $presentedMessage = $this->addWorkspaceValidationError($path, $message);
                $firstMessage ??= $presentedMessage;
            }
        }

        return $firstMessage;
    }

    private function normalizeLatestErrorNotification(string $message): void
    {
        $notifications = session()->get('filament.notifications', []);
        if (! is_array($notifications) || $notifications === []) {
            return;
        }

        $index = array_key_last($notifications);
        if ($index === null) {
            return;
        }

        $notification = $notifications[$index] ?? null;
        if (! is_array($notification) || ($notification['status'] ?? null) !== 'danger') {
            return;
        }

        $notification['body'] = $message;
        $notifications[$index] = $notification;
        session()->put('filament.notifications', $notifications);
    }

    private function addWorkspaceValidationError(string $path, string $technicalMessage): string
    {
        $presentedMessage = $this->workspaceValidationMessage($path, $technicalMessage);

        $this->workspaceValidationDetails[$path] ??= [];
        $this->workspaceValidationDetails[$path][] = $presentedMessage === $technicalMessage
            ? null
            : $technicalMessage;

        $this->addError($path, $presentedMessage);

        return $presentedMessage;
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
        if ($message === __('ui.workspace.template_incompatible')) {
            return $message;
        }

        if (str_contains($message, 'SourceDraft base state is stale')) {
            return __('ui.workspace.changed_elsewhere_body');
        }

        if (preg_match('/^evidenceData\.mentions\.(\d+)(?:\.(.+))?$/', $path, $matches) === 1) {
            return $this->mentionValidationMessage((int) $matches[1], $matches[2] ?? null, $message);
        }

        if (preg_match('/^evidenceData\.fields\.(\d+)(?:\.(.+))?$/', $path, $matches) === 1) {
            return $this->claimValidationMessage((int) $matches[1], $matches[2] ?? null, $message);
        }

        if (preg_match('/^evidenceData\.event_contexts\.(\d+)\.claims\.(\d+)(?:\.(.+))?$/', $path, $matches) === 1) {
            return __('workspace_validation.event_claim_invalid', [
                'event_number' => (int) $matches[1] + 1,
                'claim_number' => (int) $matches[2] + 1,
            ]);
        }

        if (preg_match('/^evidenceData\.event_contexts\.(\d+)(?:\.(.+))?$/', $path, $matches) === 1) {
            return $this->eventValidationMessage((int) $matches[1], $matches[2] ?? null, $message);
        }

        if (preg_match('/^data\.metadata\.(\d+)(?:\.(.+))?$/', $path, $matches) === 1) {
            return $this->metadataValidationMessage((int) $matches[1], $matches[2] ?? null, $message);
        }

        if (preg_match('/^sourceTexts\.(\d+)/', $path, $matches) === 1) {
            return __('workspace_validation.source_text_invalid', [
                'number' => (int) $matches[1] + 1,
            ]);
        }

        if ($path === 'data.uploads' || str_starts_with($path, 'data.uploads.')) {
            return __('workspace_validation.upload_invalid');
        }

        if ($path === 'data') {
            return $this->workspaceDataValidationMessage($message);
        }

        if (str_starts_with($path, 'data.')) {
            return __('workspace_validation.source_details_invalid');
        }

        return __('workspace_validation.workspace_data_invalid');
    }

    private function mentionValidationMessage(int $index, ?string $field, string $message): string
    {
        $mention = $this->mentionAtPresentationIndex($index);
        $keySuffix = $this->localKeySuffix($mention);

        if ($field === 'raw_data_json') {
            $rawData = $mention['raw_data_json'] ?? null;
            if (is_string($rawData) && trim($rawData) !== '' && ! json_validate($rawData)) {
                return __('workspace_validation.mention_json_syntax', [
                    'number' => $index + 1,
                    'key_suffix' => $keySuffix,
                ]);
            }

            if ($message === 'Mention raw data must be a JSON object.') {
                return __('workspace_validation.mention_json_object', [
                    'number' => $index + 1,
                    'key_suffix' => $keySuffix,
                ]);
            }
        }

        if ($message === 'Mention kind and local key are required.') {
            return __('workspace_validation.mention_required', [
                'number' => $index + 1,
                'key_suffix' => $keySuffix,
            ]);
        }

        return __('workspace_validation.mention_invalid', [
            'number' => $index + 1,
            'key_suffix' => $keySuffix,
        ]);
    }

    private function claimValidationMessage(int $index, ?string $field, string $message): string
    {
        if ($message === 'Supported field and subject Mention are required.') {
            return __('workspace_validation.claim_subject_required', ['number' => $index + 1]);
        }

        if ($field === 'value_raw' && $message === 'Literal fields require the raw/source value.') {
            return __('workspace_validation.claim_value_required', ['number' => $index + 1]);
        }

        if ($field === 'effective_time_raw' && $message === 'Effective time requires raw value, expression kind and start date.') {
            return __('workspace_validation.claim_effective_time_incomplete', ['number' => $index + 1]);
        }

        return __('workspace_validation.claim_invalid', ['number' => $index + 1]);
    }

    private function eventValidationMessage(int $index, ?string $field, string $message): string
    {
        $event = $this->eventAtPresentationIndex($index);
        $keySuffix = $this->localKeySuffix($event);

        if ($field === 'raw_data_json') {
            $rawData = $event['raw_data_json'] ?? null;
            if (is_string($rawData) && trim($rawData) !== '' && ! json_validate($rawData)) {
                return __('workspace_validation.event_json_syntax', [
                    'number' => $index + 1,
                    'key_suffix' => $keySuffix,
                ]);
            }

            if ($message === 'Mention raw data must be a JSON object.') {
                return __('workspace_validation.event_json_object', [
                    'number' => $index + 1,
                    'key_suffix' => $keySuffix,
                ]);
            }
        }

        return __('workspace_validation.event_invalid', [
            'number' => $index + 1,
            'key_suffix' => $keySuffix,
        ]);
    }

    private function metadataValidationMessage(int $index, ?string $field, string $message): string
    {
        if ($field === 'value') {
            return match ($message) {
                'Enter a valid integer.' => __('workspace_validation.metadata_integer_invalid', ['number' => $index + 1]),
                'Enter a valid number.' => __('workspace_validation.metadata_number_invalid', ['number' => $index + 1]),
                'Enter true or false.' => __('workspace_validation.metadata_boolean_invalid', ['number' => $index + 1]),
                default => __('workspace_validation.metadata_invalid', ['number' => $index + 1]),
            };
        }

        if ($field === 'key' && str_starts_with($message, 'Metadata keys must be unique')) {
            return __('workspace_validation.metadata_duplicate', ['number' => $index + 1]);
        }

        return __('workspace_validation.metadata_invalid', ['number' => $index + 1]);
    }

    private function workspaceDataValidationMessage(string $message): string
    {
        if (preg_match('/^Mention local key "([^"]+)" is duplicated within the Source\.$/', $message, $matches) === 1) {
            $index = $this->duplicateMentionPresentationIndex($matches[1]);
            if ($index !== null) {
                return __('workspace_validation.mention_duplicate_local_key', [
                    'number' => $index + 1,
                    'key_suffix' => $this->localKeySuffix($this->mentionAtPresentationIndex($index)),
                ]);
            }
        }

        if (preg_match('/^Subject Mention local key "([^"]+)" does not exist in this Source\.$/', $message, $matches) === 1) {
            $index = $this->directClaimIndexBy('subject_local_key', $matches[1]);
            if ($index !== null) {
                return __('workspace_validation.claim_subject_missing', ['number' => $index + 1]);
            }
        }

        if (preg_match('/^Object Mention local key "([^"]+)" does not exist in this Source\.$/', $message, $matches) === 1) {
            $index = $this->directClaimIndexBy('object_local_key', $matches[1]);
            if ($index !== null) {
                return __('workspace_validation.claim_object_missing', ['number' => $index + 1]);
            }
        }

        if (preg_match('/^Supported acquisition field "([^"]+)" requires an object Mention local key\.$/', $message, $matches) === 1) {
            $index = $this->directClaimIndexBy('field_key', $matches[1]);
            if ($index !== null) {
                return __('workspace_validation.claim_object_required', ['number' => $index + 1]);
            }
        }

        if (preg_match('/^Predicate "([^"]+)" requires a "[^"]+" (?:subject|object) Mention\.$/', $message, $matches) === 1) {
            $index = $this->directClaimIndexBy('field_key', $matches[1]);
            if ($index !== null) {
                return __('workspace_validation.claim_invalid', ['number' => $index + 1]);
            }
        }

        return __('workspace_validation.workspace_data_invalid');
    }

    /** @return array<string, mixed>|null */
    private function mentionAtPresentationIndex(int $index): ?array
    {
        return $this->evidenceRowAt('mentions', $index);
    }

    /** @return array<string, mixed>|null */
    private function eventAtPresentationIndex(int $index): ?array
    {
        return $this->evidenceRowAt('event_contexts', $index);
    }

    /** @return array<string, mixed>|null */
    private function evidenceRowAt(string $key, int $index): ?array
    {
        $evidenceData = is_array($this->evidenceData) ? $this->evidenceData : [];
        $rows = $evidenceData[$key] ?? [];
        if (! is_array($rows)) {
            return null;
        }

        $row = array_values($rows)[$index] ?? null;

        return is_array($row) ? $row : null;
    }

    /** @param  array<string, mixed>|null  $row */
    private function localKeySuffix(?array $row): string
    {
        $localKey = $row['local_key'] ?? null;

        return is_string($localKey) && trim($localKey) !== ''
            ? ' ('.trim($localKey).')'
            : '';
    }

    private function duplicateMentionPresentationIndex(string $localKey): ?int
    {
        $evidenceData = is_array($this->evidenceData) ? $this->evidenceData : [];
        $mentions = $evidenceData['mentions'] ?? [];
        if (! is_array($mentions)) {
            return null;
        }

        $found = false;
        foreach (array_values($mentions) as $index => $mention) {
            if (! is_array($mention) || ($mention['local_key'] ?? null) !== $localKey) {
                continue;
            }
            if ($found) {
                return $index;
            }
            $found = true;
        }

        return null;
    }

    private function directClaimIndexBy(string $field, string $value): ?int
    {
        $evidenceData = is_array($this->evidenceData) ? $this->evidenceData : [];
        $fields = $evidenceData['fields'] ?? [];
        if (! is_array($fields)) {
            return null;
        }

        foreach (array_values($fields) as $index => $claim) {
            if (is_array($claim) && ($claim[$field] ?? null) === $value) {
                return $index;
            }
        }

        return null;
    }
}
