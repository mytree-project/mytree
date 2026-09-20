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
        <div
            @class([
                'source-workspace-error-region' => $workspaceHasUntargetedEvidenceValidationError,
            ])
            data-mentions-claims-editor
            data-evidence-validation-targets="{{ json_encode($workspaceValidationTargets, JSON_THROW_ON_ERROR) }}"
            x-data
            x-init="
                const directItems = (repeater) => {
                    const list = repeater?.querySelector(':scope > .fi-fo-repeater-items');

                    return list
                        ? Array.from(list.children).filter((child) => child.classList.contains('fi-fo-repeater-item'))
                        : [];
                };
                const itemAt = (scope, repeaterName, index) => {
                    const repeater = scope?.querySelector(`[data-evidence-repeater='${repeaterName}']`);

                    return directItems(repeater)[index] ?? null;
                };
                const expand = (item) => {
                    if (item?.classList.contains('fi-collapsed')) {
                        item.dispatchEvent(new CustomEvent('expand'));
                    }
                };
                const expandValidationTargets = () => {
                    let targets = [];

                    try {
                        targets = JSON.parse($el.dataset.evidenceValidationTargets ?? '[]');
                    } catch {
                        return;
                    }

                    targets.forEach((target) => {
                        if (target.type === 'mention') {
                            expand(itemAt($el, 'mentions', target.index));

                            return;
                        }

                        if (target.type === 'claim') {
                            expand(itemAt($el, 'claims', target.index));

                            return;
                        }

                        const eventItem = itemAt($el, 'events', target.event_index ?? target.index);
                        expand(eventItem);

                        if (target.type === 'event_claim' && eventItem) {
                            expand(itemAt(eventItem, 'event-claims', target.claim_index));
                        }
                    });
                };

                $nextTick(expandValidationTargets);

                const validationTargetObserver = new MutationObserver(() => {
                    $nextTick(expandValidationTargets);
                });
                validationTargetObserver.observe($el, {
                    attributes: true,
                    attributeFilter: ['data-evidence-validation-targets'],
                });
            "
        >
            @if ($workspaceValidationTargets !== [])
                <style data-evidence-validation-styles>
                    @foreach ($workspaceValidationTargets as $target)
                        @if ($target['type'] === 'mention')
                            [data-mentions-claims-editor]
                            [data-evidence-repeater="mentions"] > .fi-fo-repeater-items
                            > .fi-fo-repeater-item:nth-of-type({{ $target['index'] + 1 }}) {
                                outline: 2px solid rgb(220 38 38);
                                outline-offset: 2px;
                            }
                        @elseif ($target['type'] === 'claim')
                            [data-mentions-claims-editor]
                            [data-evidence-repeater="claims"] > .fi-fo-repeater-items
                            > .fi-fo-repeater-item:nth-of-type({{ $target['index'] + 1 }}) {
                                outline: 2px solid rgb(220 38 38);
                                outline-offset: 2px;
                            }
                        @elseif ($target['type'] === 'event')
                            [data-mentions-claims-editor]
                            [data-evidence-repeater="events"] > .fi-fo-repeater-items
                            > .fi-fo-repeater-item:nth-of-type({{ $target['index'] + 1 }}) {
                                outline: 2px solid rgb(220 38 38);
                                outline-offset: 2px;
                            }
                        @else
                            [data-mentions-claims-editor]
                            [data-evidence-repeater="events"] > .fi-fo-repeater-items
                            > .fi-fo-repeater-item:nth-of-type({{ $target['event_index'] + 1 }}) {
                                outline: 2px solid rgb(220 38 38);
                                outline-offset: 2px;
                            }

                            [data-mentions-claims-editor]
                            [data-evidence-repeater="events"] > .fi-fo-repeater-items
                            > .fi-fo-repeater-item:nth-of-type({{ $target['event_index'] + 1 }})
                            [data-evidence-repeater="event-claims"] > .fi-fo-repeater-items
                            > .fi-fo-repeater-item:nth-of-type({{ $target['claim_index'] + 1 }}) {
                                outline: 2px solid rgb(220 38 38);
                                outline-offset: 2px;
                            }
                        @endif
                    @endforeach
                </style>
            @endif

            {{ $this->evidenceForm }}
        </div>
        @break

    @case('graph')
        @php($evidenceGraph = $this->evidenceGraphPresentation())

        <div class="source-evidence-graph" data-source-evidence-graph>
            <div class="source-evidence-graph-toolbar">
                <p>{{ __('ui.workspace.graph.description') }}</p>

                <button
                    type="button"
                    class="source-evidence-graph-download"
                    wire:click="downloadEvidenceGraph"
                    wire:loading.attr="disabled"
                    wire:target="downloadEvidenceGraph"
                    aria-label="{{ __('ui.workspace.graph.download') }}"
                    title="{{ __('ui.workspace.graph.download') }}"
                    @disabled($evidenceGraph['yaml'] === null)
                    data-source-evidence-graph-download
                >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 15v4h14v-4" />
                    </svg>
                </button>
            </div>

            @if ($evidenceGraph['yaml'] === null)
                <div class="source-workspace-empty" data-source-evidence-graph-error>
                    {{ $evidenceGraph['error'] }}
                </div>
            @else
                <pre class="source-evidence-graph-yaml" data-source-evidence-graph-yaml><code>{{ $evidenceGraph['yaml'] }}</code></pre>
            @endif
        </div>
        @break
@endswitch
