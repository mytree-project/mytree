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


function setCollapsibleEvidenceSelectByLabel(
    AwaitableWebpage $page,
    string $label,
    string $value,
): void {
    $selector = collapsibleEvidenceFieldSelectorByLabel($page, $label);
    $script = strtr(<<<'JS'
        (() => {
            const control = document.querySelector(__SELECTOR__);
            const value = __VALUE__;

            if (! (control instanceof HTMLSelectElement)) {
                throw new Error('Expected a native select control.');
            }

            control.value = value;
            control.dispatchEvent(new Event('input', { bubbles: true }));
            control.dispatchEvent(new Event('change', { bubbles: true }));

            return control.value;
        })()
        JS, [
        '__SELECTOR__' => json_encode($selector, JSON_THROW_ON_ERROR),
        '__VALUE__' => json_encode($value, JSON_THROW_ON_ERROR),
    ]);

    $selected = $page->script($script);

    if ($selected !== $value) {
        throw new RuntimeException("Could not select value [$value] for field [$label].");
    }
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

it('collapses persisted evidence by default with live numbered summaries and preserves unsaved state', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_valentin',
        role: 'subject',
        displayLabel: 'Valentin Wiśniewski',
    );
    $event = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::event(),
        localKey: 'event_birth',
        role: 'birth',
        displayLabel: 'Birth of Peter',
    );

    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
        value: new TextClaimValue('Valentin'),
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
        ->assertSee('Mentions')
        ->assertSee('Add mention')
        ->assertSee('Claims')
        ->assertSee('Add claim')
        ->assertSee('Events')
        ->assertSee('Add event')
        ->assertSee('Mention 1 · Valentin Wiśniewski · person_valentin')
        ->assertSee('Claim 1 · Given name · person_valentin · Valentin')
        ->assertSee('Event 1 · Birth of Peter · event_birth');

    $mentionSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Mention 1 · Valentin Wiśniewski · person_valentin');
    $claimSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Claim 1 · Given name · person_valentin · Valentin');
    $eventSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Event 1 · Birth of Peter · event_birth');
    $eventFieldSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Claim 1 · Event date · 1904-06-29');

    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);
    assertCollapsibleEvidenceItemState($page, $claimSelector, true);
    assertCollapsibleEvidenceItemState($page, $eventSelector, true);
    assertCollapsibleEvidenceItemState($page, $eventFieldSelector, true);

    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, false);

    $displayLabelSelector = setCollapsibleEvidenceField($page, 'Display label', 'Valentin Updated');
    $page->assertSee('Mention 1 · Valentin Updated · person_valentin');

    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);

    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, false);
    $page->assertValue($displayLabelSelector, 'Valentin Updated');

    toggleCollapsibleEvidenceItem($page, $claimSelector);
    assertCollapsibleEvidenceItemState($page, $claimSelector, false);
    toggleCollapsibleEvidenceItem($page, $claimSelector);
    assertCollapsibleEvidenceItemState($page, $claimSelector, true);

    toggleCollapsibleEvidenceItem($page, $eventSelector);
    assertCollapsibleEvidenceItemState($page, $eventSelector, false);
    $page->assertSee('Claim 1 · Event date · 1904-06-29');
    assertCollapsibleEvidenceItemState($page, $eventFieldSelector, true);

    toggleCollapsibleEvidenceItem($page, $eventFieldSelector);
    assertCollapsibleEvidenceItemState($page, $eventFieldSelector, false);
    assertCollapsibleEvidenceItemState($page, $eventSelector, false);

    $page->assertNoJavaScriptErrors();
});

it('preserves unrelated details state when a Claim predicate reacts', function (): void {
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
    $claimSelector = collapsibleEvidenceItemSelectorBySummary(
        $page,
        'Claim 1 · Given name · person_jan · Jan',
    );

    assertSourceWorkspaceDetailsOpen($page, $sourceDetails, true);
    assertSourceWorkspaceDetailsOpen($page, $otherTexts, false);

    toggleCollapsibleEvidenceItem($page, $claimSelector);
    assertCollapsibleEvidenceItemState($page, $claimSelector, false);

    $page->click($sourceDetails.' > summary');
    $page->click($otherTexts.' > summary');
    assertSourceWorkspaceDetailsOpen($page, $sourceDetails, false);
    assertSourceWorkspaceDetailsOpen($page, $otherTexts, true);

    setCollapsibleEvidenceSelectByLabel($page, 'Supported field', PredicateKey::PersonBirthDate->value);
    $page->assertSee('Birth date');
    assertCollapsibleEvidenceLabelPresent($page, 'Expression', true);
    assertSourceWorkspaceDetailsOpen($page, $sourceDetails, false);
    assertSourceWorkspaceDetailsOpen($page, $otherTexts, true);

    $page->click($sourceDetails.' > summary');
    $page->click($otherTexts.' > summary');
    assertSourceWorkspaceDetailsOpen($page, $sourceDetails, true);
    assertSourceWorkspaceDetailsOpen($page, $otherTexts, false);

    setCollapsibleEvidenceSelectByLabel($page, 'Supported field', PredicateKey::PersonGivenName->value);
    $page->assertSee('Given name');
    assertCollapsibleEvidenceLabelPresent($page, 'Expression', false);
    assertSourceWorkspaceDetailsOpen($page, $sourceDetails, true);
    assertSourceWorkspaceDetailsOpen($page, $otherTexts, false);

    $page->assertNoJavaScriptErrors();
});

it('expands collapsed evidence when save validation reports an error inside it', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_invalid',
        displayLabel: 'Invalid JSON example',
    );

    authenticateCollapsibleEvidenceBrowserTestUser();
    $page = openCollapsibleEvidenceWorkspace($source->id->value);

    $mentionSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Mention 1 · Invalid JSON example · person_invalid');
    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);

    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, false);
    setCollapsibleEvidenceField($page, 'Raw source-local data (JSON object)', '{"broken":');
    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);

    $page->click('form.source-acquisition-form button[type="submit"]')
        ->assertPresent('[data-source-workspace-save-errors]');

    assertCollapsibleEvidenceItemState($page, $mentionSelector, false);
    $page->assertNoJavaScriptErrors();
});

it('localizes evidence collection labels and numbered summaries in Polish', function (): void {
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
        ->assertSee('Twierdzenia')
        ->assertSee('Dodaj twierdzenie')
        ->assertSee('Zdarzenia')
        ->assertSee('Dodaj zdarzenie')
        ->assertSee('Wzmianka 1 · Jan Kowalski · person_jan')
        ->assertSee('Twierdzenie 1 · Given name · person_jan · Jan')
        ->assertSee('Zdarzenie 1 · Urodzenie Jana · event_birth');

    $eventSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Zdarzenie 1 · Urodzenie Jana · event_birth');
    assertCollapsibleEvidenceItemState($page, $eventSelector, true);
    toggleCollapsibleEvidenceItem($page, $eventSelector);
    assertCollapsibleEvidenceItemState($page, $eventSelector, false);

    $page
        ->assertSee('Twierdzenie 1 · Event date · 1904-06-29')
        ->assertNoJavaScriptErrors();
});
