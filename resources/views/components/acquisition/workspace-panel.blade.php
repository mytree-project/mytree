@props([
    'side',
    'mode',
])

@php
    $modeLabels = [
        'asset' => 'Source asset / scan',
        'transcription' => 'Transcription',
        'translation' => 'Translation',
        'evidence' => 'Mentions & Claims',
    ];
@endphp

<section {{ $attributes->class(['source-workspace-panel']) }} data-source-workspace-panel="{{ $side }}">
    <header class="source-workspace-panel-header">
        <div>
            <div class="source-workspace-panel-kicker">{{ ucfirst($side) }} panel</div>
            <div class="source-workspace-panel-title">{{ $modeLabels[$mode] ?? $mode }}</div>
        </div>

        <label class="source-workspace-mode-picker">
            <span class="sr-only">Choose {{ $side }} panel content</span>
            <select
                wire:change="setWorkspacePanel('{{ $side }}', $event.target.value)"
                aria-label="Choose {{ $side }} panel content"
            >
                @foreach ($modeLabels as $value => $label)
                    <option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </header>

    <div class="source-workspace-panel-scroll" data-source-workspace-scroll="{{ $side }}">
        {{ $slot }}
    </div>
</section>
