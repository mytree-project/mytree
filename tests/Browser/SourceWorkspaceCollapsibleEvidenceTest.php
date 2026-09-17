<?php

declare(strict_types=1);

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
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
    $page->click($itemSelector.' .fi-fo-repeater-item-header-collapsible-actions');
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

it('collapses evidence blocks with live summaries and preserves unsaved state', function (): void {
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
    $page = openCollapsibleEvidenceWorkspace($source->id->value)
        ->assertSee('Mention · person_valentin · Valentin Wiśniewski')
        ->assertSee('Given name · person_valentin · Valentin')
        ->assertSee('Event · event_birth · Birth of Peter')
        ->assertSee('Event date · 1904-06-29');

    $displayLabelSelector = setCollapsibleEvidenceField($page, 'Display label', 'Valentin Updated');

    $page->assertSee('Mention · person_valentin · Valentin Updated');

    $mentionSelector = collapsibleEvidenceItemSelectorBySummary($page, 'person_valentin');
    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);

    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, false);
    $page->assertValue($displayLabelSelector, 'Valentin Updated');

    $claimSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Given name · person_valentin · Valentin');
    toggleCollapsibleEvidenceItem($page, $claimSelector);
    assertCollapsibleEvidenceItemState($page, $claimSelector, true);

    toggleCollapsibleEvidenceItem($page, $claimSelector);
    assertCollapsibleEvidenceItemState($page, $claimSelector, false);

    $eventSelector = collapsibleEvidenceItemSelectorBySummary($page, 'event_birth');
    toggleCollapsibleEvidenceItem($page, $eventSelector);
    assertCollapsibleEvidenceItemState($page, $eventSelector, true);

    toggleCollapsibleEvidenceItem($page, $eventSelector);
    assertCollapsibleEvidenceItemState($page, $eventSelector, false);

    $eventFieldSelector = collapsibleEvidenceItemSelectorBySummary($page, 'Event date · 1904-06-29');
    toggleCollapsibleEvidenceItem($page, $eventFieldSelector);
    assertCollapsibleEvidenceItemState($page, $eventFieldSelector, true);
    assertCollapsibleEvidenceItemState($page, $eventSelector, false);

    $page->assertNoJavaScriptErrors();
});

it('expands a collapsed evidence item when save validation reports an error inside it', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_invalid',
        displayLabel: 'Invalid JSON example',
    );

    authenticateCollapsibleEvidenceBrowserTestUser();
    $page = openCollapsibleEvidenceWorkspace($source->id->value);

    setCollapsibleEvidenceField($page, 'Raw source-local data (JSON object)', '{"broken":');

    $mentionSelector = collapsibleEvidenceItemSelectorBySummary($page, 'person_invalid');
    toggleCollapsibleEvidenceItem($page, $mentionSelector);
    assertCollapsibleEvidenceItemState($page, $mentionSelector, true);

    $page->click('form.source-acquisition-form button[type="submit"]')
        ->assertPresent('[data-validation-error]');

    assertCollapsibleEvidenceItemState($page, $mentionSelector, false);
    $page->assertNoJavaScriptErrors();
});
