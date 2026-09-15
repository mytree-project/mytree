@props([
    'side',
    'mode',
])

@php
    $modeLabels = [
        'asset' => __('ui.workspace.modes.asset'),
        'transcription' => __('ui.workspace.modes.transcription'),
        'translation' => __('ui.workspace.modes.translation'),
        'evidence' => __('ui.workspace.modes.evidence'),
    ];
    $sideLabel = __('ui.workspace.sides.'.$side);
    $panelLabel = __('ui.workspace.panel', ['side' => $sideLabel]);
    $chooseLabel = __('ui.workspace.choose_panel', ['side' => $sideLabel]);
@endphp

<section {{ $attributes->class(['source-workspace-panel']) }} data-source-workspace-panel="{{ $side }}">
    <header class="source-workspace-panel-header">
        <div>
            <div class="source-workspace-panel-kicker">{{ $panelLabel }}</div>
            <div class="source-workspace-panel-title">{{ $modeLabels[$mode] ?? $mode }}</div>
        </div>

        <label class="source-workspace-mode-picker">
            <span class="sr-only">{{ $chooseLabel }}</span>
            <select
                wire:change="setWorkspacePanel('{{ $side }}', $event.target.value)"
                aria-label="{{ $chooseLabel }}"
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
