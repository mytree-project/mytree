@php
    $label = __('ui.workspace.text_kinds.'.$kind);
    $description = __('ui.workspace.text_descriptions.'.$kind);
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
                    <span>{{ __('ui.workspace.language') }}</span>
                    <input
                        type="text"
                        maxlength="35"
                        placeholder="{{ __('ui.workspace.language_placeholder') }}"
                        wire:model.blur="sourceTexts.{{ $index }}.language"
                    >
                </label>

                <button type="button" wire:click="removeSourceText({{ $index }})">
                    {{ __('ui.workspace.remove') }}
                </button>
            </div>

            <label class="source-text-content-field">
                <span>{{ __('ui.workspace.text_content_label', ['label' => $label]) }}</span>
                <textarea
                    rows="18"
                    wire:model.blur="sourceTexts.{{ $index }}.content"
                    placeholder="{{ __('ui.workspace.text_placeholder', ['label' => mb_strtolower($label)]) }}"
                ></textarea>
            </label>

            @error("sourceTexts.$index")
                <p class="source-workspace-error">{{ $message }}</p>
            @enderror
        </article>
    @endforeach

    @if (! $hasRows)
        <div class="source-workspace-empty">
            {{ __('ui.workspace.text_empty', ['label' => mb_strtolower($label)]) }}
        </div>
    @endif

    <button type="button" class="source-workspace-secondary-button" wire:click="addSourceText('{{ $kind }}')">
        {{ __('ui.workspace.text_add', ['label' => mb_strtolower($label)]) }}
    </button>
</div>
