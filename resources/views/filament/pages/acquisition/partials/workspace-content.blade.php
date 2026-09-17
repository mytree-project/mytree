@switch($mode)
    @case('asset')
        @include('filament.pages.acquisition.partials.asset-viewer')
        @break

    @case('transcription')
        @include('filament.pages.acquisition.partials.source-text-editor', [
            'kind' => \App\Domain\Acquisition\SourceTextKind::Transcription->value,
        ])
        @break

    @case('translation')
        @include('filament.pages.acquisition.partials.source-text-editor', [
            'kind' => \App\Domain\Acquisition\SourceTextKind::Translation->value,
        ])
        @break

    @case('evidence')
        @php
            $evidenceHasErrors = collect(array_keys($errors->messages()))
                ->contains(fn (string $key): bool => $key === 'evidenceData' || str_starts_with($key, 'evidenceData.'));
        @endphp
        <div
            @class(['source-workspace-error-region' => $evidenceHasErrors])
            data-mentions-claims-editor
            @if ($evidenceHasErrors)
                x-init="$nextTick(() => {
                    $el.querySelectorAll('.fi-fo-repeater-item.fi-collapsed').forEach((item) => {
                        item.querySelector(':scope > .fi-fo-repeater-item-header > .fi-fo-repeater-item-header-end-actions > .fi-fo-repeater-item-header-collapsible-actions')?.click();
                    });
                })"
            @endif
        >
            {{ $this->evidenceForm }}
        </div>
        @break
@endswitch
