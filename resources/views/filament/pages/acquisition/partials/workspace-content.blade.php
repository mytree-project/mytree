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
                const markAndExpand = (item) => {
                    if (! item) {
                        return;
                    }

                    item.classList.add('source-workspace-error-item');

                    if (item.classList.contains('fi-collapsed')) {
                        item.dispatchEvent(new CustomEvent('expand'));
                    }
                };
                const applyValidationTargets = () => {
                    $el.querySelectorAll('.source-workspace-error-item').forEach((item) => {
                        item.classList.remove('source-workspace-error-item');
                    });

                    let targets = [];

                    try {
                        targets = JSON.parse($el.dataset.evidenceValidationTargets ?? '[]');
                    } catch {
                        return;
                    }

                    targets.forEach((target) => {
                        if (target.type === 'mention') {
                            markAndExpand(itemAt($el, 'mentions', target.index));

                            return;
                        }

                        if (target.type === 'claim') {
                            markAndExpand(itemAt($el, 'claims', target.index));

                            return;
                        }

                        const eventItem = itemAt($el, 'events', target.event_index ?? target.index);
                        markAndExpand(eventItem);

                        if (target.type !== 'event_claim' || ! eventItem) {
                            return;
                        }

                        markAndExpand(itemAt(eventItem, 'event-claims', target.claim_index));
                    });
                };

                applyValidationTargets();

                const validationTargetObserver = new MutationObserver(() => {
                    $nextTick(applyValidationTargets);
                });
                validationTargetObserver.observe($el, {
                    attributes: true,
                    attributeFilter: ['data-evidence-validation-targets'],
                });
            "
        >
            {{ $this->evidenceForm }}
        </div>
        @break
@endswitch
