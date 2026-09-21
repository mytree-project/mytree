<?php

declare(strict_types=1);

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Settings\Application\ApplicationSettings;
use App\Application\Settings\Application\UpdateApplicationSettings;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Pest\Browser\Api\AwaitableWebpage;

function debugCollapsibleEvidenceCheckpoint(string $step): void
{
    static $startedAt = null;

    $now = microtime(true);
    $startedAt ??= $now;

    fwrite(STDERR, sprintf(
        "[collapsible-evidence +%.3fs] %s\n",
        $now - $startedAt,
        $step,
    ));
    fflush(STDERR);
}

function authenticateCollapsibleEvidenceBrowserTestUser(): void
{
    $guardName = config('auth.defaults.guard');

    if (! is_string($guardName) || $guardName === '') {
        throw new RuntimeException('Default authentication guard is not configured.');
    }

    $auth = app(AuthFactory::class);
    $user = User::factory()->admin()->create([
        'email' => 'collapsible-evidence-admin@example.test',
    ]);

    $auth->guard($guardName)->setUser($user);
    $auth->shouldUse($guardName);
}

function openCollapsibleEvidenceWorkspace(string $sourceId): AwaitableWebpage
{
    $pendingPage = visit('/admin/acquisition/source?source='.urlencode($sourceId));
    $page = $pendingPage->__call('assertPresent', ['[data-source-workspace]']);

    if (! $page instanceof AwaitableWebpage) {
        throw new RuntimeException('Browser visit did not resolve to an awaitable webpage.');
    }

    $page->assertPresent('[data-mentions-claims-editor]');

    return $page;
}

function collapsibleEvidenceFieldSelectorByLabel(AwaitableWebpage $page, string $label): string
{
    $script = strtr(<<<'JS'
        (() => {
            const expectedLabel = __LABEL__;
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();
            const label = Array.from(document.querySelectorAll('label')).find(
                (candidate) => normalize(candidate.textContent ?? '').startsWith(expectedLabel),
            );

            if (! label) {
                throw new Error(`Form label ${expectedLabel} was not found.`);
            }

            let control = label.htmlFor ? document.getElementById(label.htmlFor) : null;
            control ??= label.querySelector('input, textarea, select');
            control ??= label.parentElement?.querySelector('input, textarea, select') ?? null;

            if (! control) {
                throw new Error(`Form control for label ${expectedLabel} was not found.`);
            }

            if (! control.id) {
                control.id = `collapsible-evidence-field-${Math.random().toString(36).slice(2)}`;
            }

            return `#${CSS.escape(control.id)}`;
        })()
        JS, [
        '__LABEL__' => json_encode($label, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);

    $selector = $page->script($script);

    if (! is_string($selector) || $selector === '') {
        throw new RuntimeException("Could not resolve browser field selector for label [$label].");
    }

    return $selector;
}

function collapsibleEvidenceItemSelectorBySummary(AwaitableWebpage $page, string $summary): string
{
    $script = strtr(<<<'JS'
        (() => {
            const expectedSummary = __SUMMARY__;
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();
            const item = Array.from(document.querySelectorAll('.fi-fo-repeater-item')).find((candidate) => {
                const label = candidate.querySelector('.fi-fo-repeater-item-header-label');

                return label && normalize(label.textContent ?? '').includes(expectedSummary);
            });

            if (! item) {
                throw new Error(`Repeater item ${expectedSummary} was not found.`);
            }

            if (! item.id) {
                item.id = `collapsible-evidence-item-${Math.random().toString(36).slice(2)}`;
            }

            return `#${CSS.escape(item.id)}`;
        })()
        JS, [
        '__SUMMARY__' => json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);

    $selector = $page->script($script);

    if (! is_string($selector) || $selector === '') {
        throw new RuntimeException("Could not resolve repeater item selector for summary [$summary].");
    }

    return $selector;
}

function setCollapsibleEvidenceField(AwaitableWebpage $page, string $label, string $value): string
{
    $selector = collapsibleEvidenceFieldSelectorByLabel($page, $label);

    $page->fill($selector, $value)->assertValue($selector, $value);
    $page->script(sprintf('document.querySelector(%s)?.blur()', json_encode($selector, JSON_THROW_ON_ERROR)));

    return $selector;
}

function toggleCollapsibleEvidenceItem(AwaitableWebpage $page, string $itemSelector): void
{
    $page->click(
        $itemSelector.' > .fi-fo-repeater-item-header > .fi-fo-repeater-item-header-end-actions > .fi-fo-repeater-item-header-collapsible-actions',
    );
}

function assertCollapsibleEvidenceItemState(
    AwaitableWebpage $page,
    string $itemSelector,
    bool $collapsed,
): void {
    $page->assertScript(
        sprintf('document.querySelector(%s).classList.contains("fi-collapsed")', json_encode($itemSelector, JSON_THROW_ON_ERROR)),
        $collapsed,
    );
}

function setCollapsibleEvidencePredicate(
    AwaitableWebpage $page,
    string $fieldKey,
): void {
    $page->click('[data-supported-field-palette-name="field_key"] [data-supported-field-palette-trigger]');

    $selector = $page->script(strtr(<<<'JS'
        (() => {
            const fieldKey = __FIELD_KEY__;
            const panel = Array.from(document.querySelectorAll('[data-supported-field-palette-panel]'))
                .find((candidate) => candidate.offsetParent !== null);

            if (! panel) {
                throw new Error('No predicate palette is open.');
            }

            const option = panel.querySelector(
                '[data-supported-field-palette-option][data-supported-field-key="' + CSS.escape(fieldKey) + '"]',
            );

            if (! option) {
                throw new Error('Predicate is not available in the active palette: ' + fieldKey);
            }

            if (! option.id) {
                option.id = 'collapsible-evidence-predicate-' + Math.random().toString(36).slice(2);
            }

            return '#' + CSS.escape(option.id);
        })()
        JS, [
        '__FIELD_KEY__' => json_encode($fieldKey, JSON_THROW_ON_ERROR),
    ]));

    if (! is_string($selector) || $selector === '') {
        throw new RuntimeException("Could not resolve predicate palette option [$fieldKey].");
    }

    $page->click($selector);
}

function assertSourceWorkspaceDetailsOpen(
    AwaitableWebpage $page,
    string $selector,
    bool $open,
): void {
    $page->assertScript(
        sprintf('document.querySelector(%s)?.open === true', json_encode($selector, JSON_THROW_ON_ERROR)),
        $open,
    );
}

function assertCollapsibleEvidenceLabelPresent(
    AwaitableWebpage $page,
    string $label,
    bool $present,
): void {
    $script = strtr(<<<'JS'
        (() => {
            const expectedLabel = __LABEL__;
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();

            return Array.from(document.querySelectorAll('label')).some(
                (candidate) => normalize(candidate.textContent ?? '').startsWith(expectedLabel),
            );
        })()
        JS, [
        '__LABEL__' => json_encode($label, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);

    $page->assertScript($script, $present);
}

it('collapses Mention cards and their nested Claims while preserving unsaved state', function (): void {
    debugCollapsibleEvidenceCheckpoint('test:start');

    $source = app(CreateSource::class)->handle(SourceType::generic());
    debugCollapsibleEvidenceCheckpoint('fixture:source-created');
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_valentin',
        role: 'subject',
        displayLabel: 'Valentin Wiśniewski',
    );
    debugCollapsibleEvidenceCheckpoint('fixture:person-created');
    $event = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::event(),
        localKey: 'event_birth',
        role: 'birth',
        displayLabel: 'Birth of Peter',
    );
    debugCollapsibleEvidenceCheckpoint('fixture:event-created');

    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
        value: new TextClaimValue('Valentin'),
    );
    debugCollapsibleEvidenceCheckpoint('fixture:person-claim-created');
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $event->id,
        predicate: PredicateVocabulary::get(PredicateKey::EventDate),
        value: DateClaimValue::exact('1904-06-29', HistoricalDate::day(1904, 6, 29)),
    );
    debugCollapsibleEvidenceCheckpoint('fixture:event-claim-created');

    authenticateCollapsibleEvidenceBrowserTestUser();
    debugCollapsibleEvidenceCheckpoint('browser:authenticated');

    debugCollapsibleEvidenceCheckpoint('workspace:open:before');
    $page = openCollapsibleEvidenceWorkspace($source->id->value);
    debugCollapsibleEvidenceCheckpoint('workspace:open:after');

    debugCollapsibleEvidenceCheckpoint('workspace:initial-assertions:before');
    $page
        ->assertSee('Mentions')
        ->assertSee('Add mention')
        ->assertSee('person · Valentin Wiśniewski · person_valentin')
        ->assertSee('event · Birth of Peter · event_birth');
    debugCollapsibleEvidenceCheckpoint('workspace:initial-assertions:after');

    debugCollapsibleEvidenceCheckpoint('selectors:mentions:before');
    $personSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'person · Valentin Wiśniewski · person_valentin',
    );
    debugCollapsibleEvidenceCheckpoint('selectors:person:resolved');
    $eventSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'event · Birth of Peter · event_birth',
    );
    debugCollapsibleEvidenceCheckpoint('selectors:event:resolved');

    debugCollapsibleEvidenceCheckpoint('state:mentions-collapsed:before');
    assertCollapsibleEvidenceItemState($page, $personSelector, true);
    assertCollapsibleEvidenceItemState($page, $eventSelector, true);
    debugCollapsibleEvidenceCheckpoint('state:mentions-collapsed:after');

    debugCollapsibleEvidenceCheckpoint('person:expand:before');
    toggleCollapsibleEvidenceItem($page, $personSelector);
    debugCollapsibleEvidenceCheckpoint('person:expand:click-returned');
    assertCollapsibleEvidenceItemState($page, $personSelector, false);
    debugCollapsibleEvidenceCheckpoint('person:expand:asserted');
    debugCollapsibleEvidenceCheckpoint('person:claim-ui:before');
    $page
        ->assertSee('Claims')
        ->assertSee('Add claim');
    debugCollapsibleEvidenceCheckpoint('person:claim-ui:after');

    debugCollapsibleEvidenceCheckpoint('person:claim-selector:before');
    $claimSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Claim 1 · Given name · Valentin');
    debugCollapsibleEvidenceCheckpoint('person:claim-selector:resolved');
    assertCollapsibleEvidenceItemState($page, $claimSelector, true);
    debugCollapsibleEvidenceCheckpoint('person:claim-collapsed:asserted');

    debugCollapsibleEvidenceCheckpoint('person:display-label:update:before');
    $displayLabelSelector = setCollapsibleEvidenceField($page, 'Display label', 'Valentin Updated');
    debugCollapsibleEvidenceCheckpoint('person:display-label:update:return');
    $page->assertSee('person · Valentin Updated · person_valentin');
    debugCollapsibleEvidenceCheckpoint('person:display-label:update:asserted');

    // Display label is live-on-blur, so Livewire may replace the repeater DOM.
    // Re-resolve temporary DOM selectors before interacting with those cards again.
    debugCollapsibleEvidenceCheckpoint('selectors:refresh-after-livewire:before');
    $personSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'person · Valentin Updated · person_valentin',
    );
    debugCollapsibleEvidenceCheckpoint('selectors:refresh:person');
    $claimSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Claim 1 · Given name · Valentin');
    debugCollapsibleEvidenceCheckpoint('selectors:refresh:claim');
    $eventSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'event · Birth of Peter · event_birth',
    );
    debugCollapsibleEvidenceCheckpoint('selectors:refresh:event');

    debugCollapsibleEvidenceCheckpoint('person:claim-expand:before');
    toggleCollapsibleEvidenceItem($page, $claimSelector);
    debugCollapsibleEvidenceCheckpoint('person:claim-expand:click-returned');
    assertCollapsibleEvidenceItemState($page, $claimSelector, false);
    debugCollapsibleEvidenceCheckpoint('person:claim-expand:asserted');

    debugCollapsibleEvidenceCheckpoint('person:claim-collapse:before');
    toggleCollapsibleEvidenceItem($page, $claimSelector);
    debugCollapsibleEvidenceCheckpoint('person:claim-collapse:click-returned');
    assertCollapsibleEvidenceItemState($page, $claimSelector, true);
    debugCollapsibleEvidenceCheckpoint('person:claim-collapse:asserted');

    debugCollapsibleEvidenceCheckpoint('person:collapse:before');
    toggleCollapsibleEvidenceItem($page, $personSelector);
    debugCollapsibleEvidenceCheckpoint('person:collapse:click-returned');
    assertCollapsibleEvidenceItemState($page, $personSelector, true);
    debugCollapsibleEvidenceCheckpoint('person:collapse:asserted');

    debugCollapsibleEvidenceCheckpoint('person:re-expand:before');
    toggleCollapsibleEvidenceItem($page, $personSelector);
    debugCollapsibleEvidenceCheckpoint('person:re-expand:click-returned');
    assertCollapsibleEvidenceItemState($page, $personSelector, false);
    debugCollapsibleEvidenceCheckpoint('person:re-expand:asserted');

    debugCollapsibleEvidenceCheckpoint('person:display-label:verify:before');
    $displayLabelSelector = collapsibleEvidenceFieldSelectorByLabel($page, 'Display label');
    $page->assertValue($displayLabelSelector, 'Valentin Updated');
    debugCollapsibleEvidenceCheckpoint('person:display-label:verify:after');

    debugCollapsibleEvidenceCheckpoint('event:expand:before');
    toggleCollapsibleEvidenceItem($page, $eventSelector);
    debugCollapsibleEvidenceCheckpoint('event:expand:click-returned');
    assertCollapsibleEvidenceItemState($page, $eventSelector, false);
    debugCollapsibleEvidenceCheckpoint('event:expand:asserted');

    debugCollapsibleEvidenceCheckpoint('event:claim-selector:before');
    $eventClaimSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'Claim 1 · Event date · 1904-06-29',
    );
    debugCollapsibleEvidenceCheckpoint('event:claim-selector:resolved');
    assertCollapsibleEvidenceItemState($page, $eventClaimSelector, true);
    debugCollapsibleEvidenceCheckpoint('event:claim-collapsed:asserted');

    debugCollapsibleEvidenceCheckpoint('event:claim-expand:before');
    toggleCollapsibleEvidenceItem($page, $eventClaimSelector);
    debugCollapsibleEvidenceCheckpoint('event:claim-expand:click-returned');
    assertCollapsibleEvidenceItemState($page, $eventClaimSelector, false);
    debugCollapsibleEvidenceCheckpoint('event:claim-expand:asserted');

    debugCollapsibleEvidenceCheckpoint('javascript-errors:before');
    $page->assertNoJavaScriptErrors();
    debugCollapsibleEvidenceCheckpoint('test:complete');
});

it('preserves unrelated details state when a nested Claim predicate reacts', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_jan',
        displayLabel: 'Jan Kowalski',
    );

    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
        value: new TextClaimValue('Jan'),
    );

    authenticateCollapsibleEvidenceBrowserTestUser();
    $page = openCollapsibleEvidenceWorkspace($source->id->value);

    $sourceDetails = '[data-source-workspace-source-details]';
    $otherTexts = '[data-source-workspace-other-texts]';
    $mentionSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'Mention 1 · person · Jan Kowalski · person_jan',
    );
    toggleCollapsibleEvidenceItem($page, $mentionSelector);

    $claimSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Claim 1 · Given name · Jan');
    toggleCollapsibleEvidenceItem($page, $claimSelector);

    $page
        ->assertPresent($sourceDetails)
        ->assertPresent($otherTexts);

    $page->click($sourceDetails.' > summary');
    $page->click($otherTexts.' > summary');
    assertSourceWorkspaceDetailsOpen($page, $sourceDetails, false);
    assertSourceWorkspaceDetailsOpen($page, $otherTexts, true);

    setCollapsibleEvidencePredicate($page, PredicateKey::PersonBirthDate->value);
    $page->assertSee('Birth date');
    assertCollapsibleEvidenceLabelPresent($page, 'Expression', true);
    assertSourceWorkspaceDetailsOpen($page, $sourceDetails, false);
    assertSourceWorkspaceDetailsOpen($page, $otherTexts, true);

    setCollapsibleEvidencePredicate($page, PredicateKey::PersonGivenName->value);
    $page->assertSee('Given name');
    assertCollapsibleEvidenceLabelPresent($page, 'Expression', false);

    $page->assertNoJavaScriptErrors();
});

it('expands a collapsed Mention when save validation reports an error inside it', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_invalid',
        displayLabel: 'Invalid JSON example',
    );

    authenticateCollapsibleEvidenceBrowserTestUser();
    $page = openCollapsibleEvidenceWorkspace($source->id->value);

    $mentionSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'Mention 1 · person · Invalid JSON example · person_invalid',
    );
    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);

    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    setCollapsibleEvidenceField($page, 'Raw source-local data (JSON object)', '{"broken":');
    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);

    $page->click('form.source-acquisition-form button[type="submit"]')
        ->assertPresent('[data-source-workspace-save-errors]');

    assertCollapsibleEvidenceItemState($page, $mentionSelector, false);
    $page->assertNoJavaScriptErrors();
});

it('localizes the unified Mention and nested Claim collections in Polish', function (): void {
    app(UpdateApplicationSettings::class)->handle(
        new ApplicationSettings(defaultLocale: 'pl'),
        changedBy: null,
    );

    $source = app(CreateSource::class)->handle(SourceType::generic());
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_jan',
        displayLabel: 'Jan Kowalski',
    );
    $event = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::event(),
        localKey: 'event_birth',
        displayLabel: 'Urodzenie Jana',
    );

    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
        value: new TextClaimValue('Jan'),
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $event->id,
        predicate: PredicateVocabulary::get(PredicateKey::EventDate),
        value: DateClaimValue::exact('1904-06-29', HistoricalDate::day(1904, 6, 29)),
    );

    authenticateCollapsibleEvidenceBrowserTestUser();
    $page = openCollapsibleEvidenceWorkspace($source->id->value);

    $page
        ->assertSee('Wzmianki')
        ->assertSee('Dodaj wzmiankę')
        ->assertSee('person · Jan Kowalski · person_jan')
        ->assertSee('event · Urodzenie Jana · event_birth');

    $eventSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'event · Urodzenie Jana · event_birth',
    );
    toggleCollapsibleEvidenceItem($page, $eventSelector);

    $page
        ->assertSee('Twierdzenia')
        ->assertSee('Dodaj twierdzenie')
        ->assertSee('Twierdzenie 1 · Event date · 1904-06-29')
        ->assertNoJavaScriptErrors();
});
