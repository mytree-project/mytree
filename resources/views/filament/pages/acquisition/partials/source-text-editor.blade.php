@php
    $isTranslation = $kind === \App\Domain\Acquisition\SourceTextKind::Translation->value;
    $label = $isTranslation ? 'Translation' : ($kind === \App\Domain\Acquisition\SourceTextKind::Transcription->value ? 'Transcription' : ucfirst(str_replace('_', ' ', $kind)));
    $description = $isTranslation
        ? 'Derived representation. Keep it separate from the transcription and record the target language.'
        : ($kind === \App\Domain\Acquisition\SourceTextKind::Transcription->value
            ? 'Source representation. Enter what the document says without replacing it with an interpretation.'
            : 'Additional SourceText representation retained in Source history.');
    $hasRows = false;
@endphp

<div class="source-text-editor" data-source-text-kind="{{ $kind }}">
    <div class="source-text-editor-intro">
        <strong>{{ $label }}</strong>
        <p>{{ $description }}</p>
    </div>

    @foreach ($sourceTexts as $index => $text)
        @continue(($text['kind'] ?? null) !== $kind)
        @php $hasRows = true; @endphp

        <article class="source-text-card" wire:key="source-text-{{ $kind }}-{{ $text['id'] ?? 'new-'.$index }}-{{ $index }}">
            <div class="source-text-card-toolbar">
                <label>
                    <span>Language</span>
                    <input
                        type="text"
                        maxlength="35"
                        placeholder="e.g. pl, ru, la"
                        wire:model.blur="sourceTexts.{{ $index }}.language"
                    >
                </label>

                <button type="button" wire:click="removeSourceText({{ $index }})">
                    Remove
                </button>
            </div>

            <label class="source-text-content-field">
                <span>{{ $label }} text</span>
                <textarea
                    rows="18"
                    wire:model.blur="sourceTexts.{{ $index }}.content"
                    placeholder="Enter {{ strtolower($label) }}…"
                ></textarea>
            </label>

            @error("sourceTexts.$index")
                <p class="source-workspace-error">{{ $message }}</p>
            @enderror
        </article>
    @endforeach

    @if (! $hasRows)
        <div class="source-workspace-empty">
            No {{ strtolower($label) }} has been recorded for this Source yet.
        </div>
    @endif

    <button type="button" class="source-workspace-secondary-button" wire:click="addSourceText('{{ $kind }}')">
        Add {{ strtolower($label) }}
    </button>
</div>
