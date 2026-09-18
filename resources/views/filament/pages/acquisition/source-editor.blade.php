<x-filament-panels::page>
    @php
        $sourceDetailsHasErrors = collect(array_keys($errors->messages()))
            ->contains(fn (string $key): bool => $key === 'data' || str_starts_with($key, 'data.'));
    @endphp

    <form wire:submit="save" class="source-acquisition-form">
        @if ($errors->any())
            <div
                class="source-workspace-save-errors"
                role="alert"
                aria-live="assertive"
                data-source-workspace-save-errors
            >
                <strong>{{ __('ui.workspace.save_failed') }}</strong>
                <ul>
                    @foreach ($errors->messages() as $path => $messages)
                        @foreach ($messages as $messageIndex => $message)
                            @php
                                $technicalDetail = $workspaceValidationDetails[$path][$messageIndex] ?? null;
                            @endphp
                            <li>
                                <span>{{ $message }}</span>
                                @if (is_string($technicalDetail) && trim($technicalDetail) !== '')
                                    <details class="source-workspace-error-details">
                                        <summary>{{ __('workspace_validation.technical_details') }}</summary>
                                        <code>{{ $technicalDetail }}</code>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        <div
            x-data="{ split: 50, dragging: false }"
            x-ref="workspace"
            :style="`--source-workspace-split: ${split}%`"
            class="source-workspace"
            data-source-workspace
            @pointermove.window="
                if (dragging) {
                    const rect = $refs.workspace.getBoundingClientRect();
                    split = Math.min(75, Math.max(25, (($event.clientX - rect.left) / rect.width) * 100));
                }
            "
            @pointerup.window="dragging = false"
            @pointercancel.window="dragging = false"
        >
            <div class="source-workspace-side source-workspace-left">
                <x-acquisition.workspace-panel side="left" :mode="$leftPanel">
                    @include('filament.pages.acquisition.partials.workspace-content', ['mode' => $leftPanel])
                </x-acquisition.workspace-panel>
            </div>

            <div
                class="source-workspace-resizer"
                role="separator"
                aria-label="{{ __('ui.workspace.resize_panels') }}"
                aria-orientation="vertical"
                aria-valuemin="25"
                aria-valuemax="75"
                :aria-valuenow="Math.round(split)"
                tabindex="0"
                data-source-workspace-resizer
                @pointerdown.prevent="dragging = true"
                @keydown.left.prevent="split = Math.max(25, split - 5)"
                @keydown.right.prevent="split = Math.min(75, split + 5)"
                @keydown.home.prevent="split = 25"
                @keydown.end.prevent="split = 75"
            >
                <span aria-hidden="true"></span>
            </div>

            <div class="source-workspace-side source-workspace-right">
                <x-acquisition.workspace-panel side="right" :mode="$rightPanel">
                    @include('filament.pages.acquisition.partials.workspace-content', ['mode' => $rightPanel])
                </x-acquisition.workspace-panel>
            </div>
        </div>

        <details
            @class([
                'source-workspace-details',
                'source-workspace-error-region' => $sourceDetailsHasErrors,
            ])
            open
        >
            <summary>{{ __('ui.workspace.source_details_assets') }}</summary>
            <div class="source-workspace-details-content">
                {{ $this->form }}
            </div>
        </details>

        <details class="source-workspace-details">
            <summary>{{ __('ui.workspace.other_texts') }}</summary>
            <div class="source-workspace-details-content source-workspace-other-texts">
                @include('filament.pages.acquisition.partials.source-text-editor', [
                    'kind' => \App\Domain\Acquisition\SourceTextKind::Summary->value,
                ])
                @include('filament.pages.acquisition.partials.source-text-editor', [
                    'kind' => \App\Domain\Acquisition\SourceTextKind::ResearchNote->value,
                ])
            </div>
        </details>

        <div class="source-workspace-actions">
            <x-filament::button type="submit">
                {{ __('ui.workspace.save_source') }}
            </x-filament::button>

            <x-filament::button
                tag="a"
                color="gray"
                href="{{ \App\Filament\Pages\Acquisition\Sources::getUrl() }}"
            >
                {{ __('ui.workspace.back_to_sources') }}
            </x-filament::button>
        </div>
    </form>

    <style>
        .source-acquisition-form { display: grid; gap: 1rem; }
        .source-workspace-save-errors {
            display: grid;
            gap: .45rem;
            border: 1px solid rgb(220 38 38);
            border-radius: .75rem;
            padding: .85rem 1rem;
            background: rgb(254 242 242);
            color: rgb(153 27 27);
        }
        .source-workspace-save-errors ul { margin: 0; padding-left: 1.25rem; list-style: disc; }
        .source-workspace-error-details { margin-top: .25rem; font-size: .78rem; }
        .source-workspace-error-details summary { cursor: pointer; font-weight: 600; }
        .source-workspace-error-details code { display: block; margin-top: .25rem; white-space: pre-wrap; overflow-wrap: anywhere; }
        .dark .source-workspace-save-errors { background: rgb(69 10 10); color: rgb(254 202 202); }
        .source-workspace-error-region { outline: 2px solid rgb(220 38 38); outline-offset: 2px; }
        .source-workspace { display: flex; flex-direction: column; gap: .75rem; min-width: 0; }
        .source-workspace-side { min-width: 0; }
        .source-workspace-panel {
            display: flex;
            min-height: 32rem;
            height: 100%;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: .75rem;
            background: rgb(255 255 255);
        }
        .dark .source-workspace-panel { border-color: rgb(55 65 81); background: rgb(17 24 39); }
        .source-workspace-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 1rem;
            border-bottom: 1px solid rgb(229 231 235);
        }
        .dark .source-workspace-panel-header { border-color: rgb(55 65 81); }
        .source-workspace-panel-kicker { font-size: .72rem; color: rgb(107 114 128); text-transform: uppercase; letter-spacing: .08em; }
        .source-workspace-panel-title { font-weight: 650; }
        .source-workspace-mode-picker select,
        .source-text-card input,
        .source-text-card textarea {
            width: 100%;
            border: 1px solid rgb(209 213 219);
            border-radius: .5rem;
            background: transparent;
            padding: .5rem .65rem;
        }
        .source-workspace-mode-picker { min-width: 12rem; }
        .dark .source-workspace-mode-picker select,
        .dark .source-text-card input,
        .dark .source-text-card textarea { border-color: rgb(75 85 99); }
        .source-workspace-panel-scroll { flex: 1; min-height: 0; overflow: auto; padding: 1rem; }
        .source-workspace-resizer { display: none; }
        .source-workspace-details-region { border-radius: .75rem; }
        .source-workspace-details {
            border: 1px solid rgb(229 231 235);
            border-radius: .75rem;
            background: rgb(255 255 255);
        }
        .dark .source-workspace-details { border-color: rgb(55 65 81); background: rgb(17 24 39); }
        .source-workspace-details > summary { cursor: pointer; padding: .9rem 1rem; font-weight: 650; }
        .source-workspace-details-content { padding: 0 1rem 1rem; }
        .source-workspace-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .source-workspace-other-texts { display: grid; gap: 1rem; }
        .source-text-editor { display: grid; gap: .9rem; }
        .source-text-editor-intro p { margin-top: .2rem; color: rgb(107 114 128); font-size: .875rem; }
        .source-text-card { display: grid; gap: .75rem; border: 1px solid rgb(229 231 235); border-radius: .65rem; padding: .85rem; }
        .dark .source-text-card { border-color: rgb(55 65 81); }
        .source-text-card-toolbar { display: flex; gap: .75rem; align-items: end; justify-content: space-between; }
        .source-text-card-toolbar label { display: grid; gap: .25rem; width: min(16rem, 70%); font-size: .82rem; }
        .source-text-card-toolbar button { font-size: .82rem; text-decoration: underline; }
        .source-text-content-field { display: grid; gap: .3rem; font-size: .82rem; }
        .source-text-content-field textarea { min-height: 20rem; resize: vertical; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; line-height: 1.5; }
        .source-workspace-empty { border: 1px dashed rgb(209 213 219); border-radius: .65rem; padding: 1rem; color: rgb(107 114 128); }
        .dark .source-workspace-empty { border-color: rgb(75 85 99); }
        .source-workspace-secondary-button,
        .source-asset-toolbar button {
            border: 1px solid rgb(209 213 219);
            border-radius: .5rem;
            padding: .45rem .7rem;
            width: fit-content;
        }
        .dark .source-workspace-secondary-button,
        .dark .source-asset-toolbar button { border-color: rgb(75 85 99); }
        .source-workspace-error { color: rgb(185 28 28); font-size: .84rem; }
        .source-asset-viewer, .source-asset-viewer-shell { height: 100%; min-height: 26rem; }
        .source-asset-viewer-shell { display: flex; flex-direction: column; gap: .65rem; }
        .source-asset-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .65rem; }
        .source-asset-navigation, .source-asset-zoom { display: flex; align-items: center; gap: .45rem; }
        .source-asset-toolbar button:disabled { opacity: .45; cursor: not-allowed; }
        .source-asset-meta { display: flex; flex-wrap: wrap; gap: .45rem .9rem; align-items: baseline; font-size: .82rem; }
        .source-asset-meta span { color: rgb(107 114 128); }
        .source-asset-meta a { text-decoration: underline; }
        .source-asset-stage { flex: 1; min-height: 0; overflow: hidden; border-radius: .5rem; background: rgb(243 244 246); }
        .dark .source-asset-stage { background: rgb(3 7 18); }
        .source-asset-image-pan { width: 100%; height: 100%; min-height: 28rem; overflow: auto; }
        .source-asset-image-pan img { display: block; max-width: none; }
        .source-asset-pdf { width: 100%; height: 100%; min-height: 32rem; border: 0; }
        .source-asset-media { width: 100%; max-height: 100%; }
        .source-asset-audio { width: min(100%, 36rem); margin: 2rem; }

        @media (min-width: 1024px) {
            .source-workspace { flex-direction: row; gap: 0; height: min(72vh, 58rem); min-height: 38rem; }
            .source-workspace-left { flex: 0 0 var(--source-workspace-split); }
            .source-workspace-right { flex: 1 1 auto; }
            .source-workspace-panel { min-height: 0; }
            .source-workspace-resizer {
                display: flex;
                flex: 0 0 1rem;
                align-items: center;
                justify-content: center;
                cursor: col-resize;
                touch-action: none;
                outline-offset: -2px;
            }
            .source-workspace-resizer span { display: block; width: 3px; height: 3rem; border-radius: 99px; background: rgb(156 163 175); }
            .source-workspace-resizer:focus-visible { outline: 2px solid currentColor; border-radius: .4rem; }
        }
    </style>
</x-filament-panels::page>
