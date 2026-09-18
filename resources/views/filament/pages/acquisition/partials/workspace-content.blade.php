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
@endswitch
