<?php

declare(strict_types=1);

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Settings\Application\ApplicationSettings;
use App\Application\Settings\Application\UpdateApplicationSettings;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Pest\Browser\Api\AwaitableWebpage;

function authenticateSourceWorkspaceBrowserTestUser(): void
{
    $guardName = config('auth.defaults.guard');

    if (! is_string($guardName) || $guardName === '') {
        throw new RuntimeException('Default authentication guard is not configured.');
    }

    $auth = app(AuthFactory::class);
    $user = User::factory()->admin()->create([
        'email' => 'browser-admin@example.test',
    ]);

    $auth->guard($guardName)->setUser($user);
    $auth->shouldUse($guardName);
}

function openSourceWorkspaceForBrowserTest(string $sourceId): AwaitableWebpage
{
    app(UpdateApplicationSettings::class)->handle(
        new ApplicationSettings(defaultLocale: 'pl'),
        changedBy: null,
    );

    $pendingPage = visit('/admin/acquisition/source?source='.urlencode($sourceId));
    $page = $pendingPage->__call('assertPresent', ['[data-source-workspace]']);

    if (! $page instanceof AwaitableWebpage) {
        throw new RuntimeException('Browser visit did not resolve to an awaitable webpage.');
    }

    $page->assertPresent('[data-mentions-claims-editor]');

    return $page;
}

function sourceWorkspaceBrowserFieldSelectorByLabel(
    AwaitableWebpage $page,
    string $label,
    ?string $scopeSelector = null,
): string {
    $script = strtr(<<<'JS'
        (() => {
            const expectedLabel = __LABEL__;
            const scopeSelector = __SCOPE__;
            const scope = scopeSelector === null ? document : document.querySelector(scopeSelector);
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();

            if (! scope) {
                throw new Error(`Form scope ${scopeSelector} was not found.`);
            }

            const label = Array.from(scope.querySelectorAll('label')).find(
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
                control.id = `browser-field-${Math.random().toString(36).slice(2)}`;
            }

            return `#${CSS.escape(control.id)}`;
        })()
        JS, [
        '__LABEL__' => json_encode($label, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        '__SCOPE__' => json_encode($scopeSelector, JSON_THROW_ON_ERROR),
    ]);

    $selector = $page->script($script);

    if (! is_string($selector) || $selector === '') {
        throw new RuntimeException("Could not resolve browser field selector for label [$label].");
    }

    return $selector;
}

function sourceWorkspaceEvidenceItemSelector(
    AwaitableWebpage $page,
    string $repeaterName,
    int $index,
    ?string $scopeSelector = null,
): string {
    $scopeSelector ??= '[data-mentions-claims-editor]';
    $selector = sprintf(
        '%s [data-evidence-repeater="%s"] > .fi-fo-repeater-items > .fi-fo-repeater-item:nth-of-type(%d)',
        $scopeSelector,
        $repeaterName,
        $index + 1,
    );

    $page->assertPresent($selector);

    return $selector;
}

function assertSourceWorkspaceEvidenceItemValidationState(
    AwaitableWebpage $page,
    string $itemSelector,
    bool $hasError,
    ?bool $collapsed = null,
): void {
    $selector = json_encode($itemSelector, JSON_THROW_ON_ERROR);
    $state = $page->script(
        "(() => {
            const item = document.querySelector($selector);

            if (! item) {
                return null;
            }

            const style = getComputedStyle(item);

            return {
                hasError: style.outlineStyle !== 'none' && style.outlineWidth !== '0px',
                collapsed: item.classList.contains('fi-collapsed'),
            };
        })()",
    );

    if (! is_array($state)) {
        throw new RuntimeException("Evidence item [$itemSelector] disappeared from the page.");
    }

    expect($state['hasError'] ?? null)->toBe($hasError);

    if ($collapsed !== null) {
        expect($state['collapsed'] ?? null)->toBe($collapsed);
    }
}

function toggleSourceWorkspaceEvidenceItem(
    AwaitableWebpage $page,
    string $itemSelector,
): void {
    $page->click(
        $itemSelector.' > .fi-fo-repeater-item-header > .fi-fo-repeater-item-header-end-actions > .fi-fo-repeater-item-header-collapsible-actions',
    );
}

function expandSourceWorkspaceEvidenceItem(
    AwaitableWebpage $page,
    string $summaryFragment,
): void {
    $script = strtr(<<<'JS'
        (() => {
            const expectedSummary = __SUMMARY__;
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();
            const item = Array.from(document.querySelectorAll('.fi-fo-repeater-item')).find((candidate) => {
                const label = candidate.querySelector(':scope > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label');

                return label && normalize(label.textContent ?? '').includes(expectedSummary);
            });

            if (! item) {
                throw new Error(`Repeater item ${expectedSummary} was not found.`);
            }

            if (! item.id) {
                item.id = `source-workspace-validation-item-${Math.random().toString(36).slice(2)}`;
            }

            return `#${CSS.escape(item.id)}`;
        })()
        JS, [
        '__SUMMARY__' => json_encode($summaryFragment, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);

    $itemSelector = $page->script($script);

    if (! is_string($itemSelector) || $itemSelector === '') {
        throw new RuntimeException("Could not resolve evidence item selector for [$summaryFragment].");
    }

    $isCollapsed = $page->script(sprintf(
        'document.querySelector(%s)?.classList.contains("fi-collapsed") ?? false',
        json_encode($itemSelector, JSON_THROW_ON_ERROR),
    ));

    if ($isCollapsed !== true) {
        return;
    }

    toggleSourceWorkspaceEvidenceItem($page, $itemSelector);
    $page->assertScript(
        sprintf(
            '!document.querySelector(%s).classList.contains("fi-collapsed")',
            json_encode($itemSelector, JSON_THROW_ON_ERROR),
        ),
        true,
    );
}

function fillSourceWorkspaceBrowserField(
    AwaitableWebpage $page,
    string $label,
    string $value,
    ?string $scopeSelector = null,
): void {
    $selector = sourceWorkspaceBrowserFieldSelectorByLabel($page, $label, $scopeSelector);

    $page->fill($selector, $value);
    $page->assertValue($selector, $value);
}

function submitSourceWorkspaceBrowserForm(AwaitableWebpage $page): AwaitableWebpage
{
    $page->click('form.source-acquisition-form button[type="submit"]');

    return $page;
}

it('scopes Mention JSON validation styling and moves it when the failing Mention changes', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_valentin',
        role: 'declarant',
        displayLabel: 'Valentin Wiśniewski',
    );
    app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_anna',
        role: 'witness',
        displayLabel: 'Anna Wiśniewska',
    );

    authenticateSourceWorkspaceBrowserTestUser();

    $page = openSourceWorkspaceForBrowserTest($source->id->value);
    $firstMention = sourceWorkspaceEvidenceItemSelector($page, 'mentions', 0);
    $secondMention = sourceWorkspaceEvidenceItemSelector($page, 'mentions', 1);

    expandSourceWorkspaceEvidenceItem($page, 'person_valentin');
    fillSourceWorkspaceBrowserField(
        $page,
        'Raw source-local data (JSON object)',
        '{"broken":',
        $firstMention,
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Błąd składni JSON w Mention nr 1 (person_valentin).')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            false,
        );

    assertSourceWorkspaceEvidenceItemValidationState($page, $firstMention, hasError: true, collapsed: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $secondMention, hasError: false, collapsed: true);

    fillSourceWorkspaceBrowserField(
        $page,
        'Raw source-local data (JSON object)',
        '{}',
        $firstMention,
    );
    expandSourceWorkspaceEvidenceItem($page, 'person_anna');
    fillSourceWorkspaceBrowserField(
        $page,
        'Raw source-local data (JSON object)',
        '{"also-broken":',
        $secondMention,
    );
    toggleSourceWorkspaceEvidenceItem($page, $secondMention);
    assertSourceWorkspaceEvidenceItemValidationState($page, $secondMention, hasError: false, collapsed: true);

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Błąd składni JSON w Mention nr 2 (person_anna).')
        ->assertVisible('[data-source-workspace-save-errors]');

    assertSourceWorkspaceEvidenceItemValidationState($page, $firstMention, hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $secondMention, hasError: true, collapsed: false);

    $page
        ->assertScript(
            "Array.from(document.querySelectorAll('textarea')).some((textarea) => textarea.value === '{\"also-broken\":')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('routes stale Claim subject errors to only the failing Claim after a Mention key rename', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_subject',
    );
    $otherPerson = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_other',
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
        value: new TextClaimValue('rolnik'),
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $otherPerson->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
        value: new TextClaimValue('kowal'),
    );

    authenticateSourceWorkspaceBrowserTestUser();

    $page = openSourceWorkspaceForBrowserTest($source->id->value);
    $renamedMention = sourceWorkspaceEvidenceItemSelector($page, 'mentions', 0);
    $otherMention = sourceWorkspaceEvidenceItemSelector($page, 'mentions', 1);
    $failingClaim = sourceWorkspaceEvidenceItemSelector($page, 'claims', 0);
    $validClaim = sourceWorkspaceEvidenceItemSelector($page, 'claims', 1);

    expandSourceWorkspaceEvidenceItem($page, 'person_subject');
    fillSourceWorkspaceBrowserField(
        $page,
        'Local key',
        'person_renamed',
        $renamedMention,
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Claim nr 1 odwołuje się do nieistniejącego Mention jako podmiotu.')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            false,
        );

    assertSourceWorkspaceEvidenceItemValidationState($page, $renamedMention, hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $otherMention, hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $failingClaim, hasError: true, collapsed: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $validClaim, hasError: false, collapsed: true);

    $page
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'person_renamed')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('marks the failing Event Claim and its enclosing Event without marking sibling evidence', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $place = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::place(),
        localKey: 'place_original',
        displayLabel: 'Original place',
    );
    $event = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::event(),
        localKey: 'event_birth',
        displayLabel: 'Birth event',
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $event->id,
        predicate: PredicateVocabulary::get(PredicateKey::EventPlace),
        objectMentionId: $place->id,
    );

    authenticateSourceWorkspaceBrowserTestUser();

    $page = openSourceWorkspaceForBrowserTest($source->id->value);
    $placeMention = sourceWorkspaceEvidenceItemSelector($page, 'mentions', 0);
    $eventItem = sourceWorkspaceEvidenceItemSelector($page, 'events', 0);
    $eventClaim = sourceWorkspaceEvidenceItemSelector($page, 'event-claims', 0, $eventItem);

    expandSourceWorkspaceEvidenceItem($page, 'place_original');
    fillSourceWorkspaceBrowserField(
        $page,
        'Local key',
        'place_renamed',
        $placeMention,
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Claim nr 1 w Event nr 1 zawiera nieprawidłowe dane.')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            false,
        );

    assertSourceWorkspaceEvidenceItemValidationState($page, $placeMention, hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $eventItem, hasError: true, collapsed: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $eventClaim, hasError: true, collapsed: false);

    $page->assertNoJavaScriptErrors();
});

it('routes metadata value errors to Source details instead of Mentions and Claims', function (): void {
    $source = app(CreateSource::class)->handle(
        SourceType::generic(),
        metadata: new SourceMetadata(['record_number' => 1]),
    );

    authenticateSourceWorkspaceBrowserTestUser();

    $page = openSourceWorkspaceForBrowserTest($source->id->value);

    fillSourceWorkspaceBrowserField(
        $page,
        'Value',
        'abc',
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Pole metadanych nr 1 wymaga prawidłowej liczby całkowitej.')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'abc')",
            true,
        )
        ->assertNoJavaScriptErrors();
});
