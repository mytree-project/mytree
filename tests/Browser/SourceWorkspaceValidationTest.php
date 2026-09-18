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

function sourceWorkspaceBrowserDebugEnabled(): bool
{
    return getenv('MYTREE_BROWSER_DEBUG') === '1';
}

function sourceWorkspaceBrowserDebugCheckpoint(string $message): void
{
    if (! sourceWorkspaceBrowserDebugEnabled()) {
        return;
    }

    static $startedAt = null;
    $startedAt ??= microtime(true);

    file_put_contents(
        'php://stderr',
        sprintf("[browser-debug +%.3fs] %s\n", microtime(true) - $startedAt, $message),
        FILE_APPEND,
    );
}

function sourceWorkspaceBrowserDebugScreenshot(
    AwaitableWebpage $page,
    string $name,
): void {
    if (! sourceWorkspaceBrowserDebugEnabled()) {
        return;
    }

    sourceWorkspaceBrowserDebugCheckpoint("screenshot:$name:before");
    $page->screenshot(fullPage: false, filename: "debug-$name");
    sourceWorkspaceBrowserDebugCheckpoint("screenshot:$name:after");
}

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

    $visitOptions = [];
    if (sourceWorkspaceBrowserDebugEnabled()) {
        $videoDirectory = base_path('tests/Browser/Videos');
        if (! is_dir($videoDirectory) && ! mkdir($videoDirectory, 0755, true) && ! is_dir($videoDirectory)) {
            throw new RuntimeException("Could not create browser debug video directory [$videoDirectory].");
        }

        $visitOptions['recordVideo'] = ['dir' => $videoDirectory];
    }

    sourceWorkspaceBrowserDebugCheckpoint('open:visit:before');
    $pendingPage = visit('/admin/acquisition/source?source='.urlencode($sourceId), $visitOptions);
    sourceWorkspaceBrowserDebugCheckpoint('open:visit:created');
    $page = $pendingPage->__call('assertPresent', ['[data-source-workspace]']);
    sourceWorkspaceBrowserDebugCheckpoint('open:workspace-present');

    if (! $page instanceof AwaitableWebpage) {
        throw new RuntimeException('Browser visit did not resolve to an awaitable webpage.');
    }

    $page->assertPresent('[data-mentions-claims-editor]');
    sourceWorkspaceBrowserDebugCheckpoint('open:evidence-editor-present');
    sourceWorkspaceBrowserDebugScreenshot($page, '01-opened');

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

    sourceWorkspaceBrowserDebugCheckpoint("selector:$repeaterName:$index:before");
    $page->assertPresent($selector);
    sourceWorkspaceBrowserDebugCheckpoint("selector:$repeaterName:$index:present");

    return $selector;
}

/**
 * @return array{selector: string, number: int}
 */
function sourceWorkspaceEvidenceItemBySummary(
    AwaitableWebpage $page,
    string $repeaterName,
    string $summaryFragment,
    ?string $scopeSelector = null,
): array {
    $scopeSelector ??= '[data-mentions-claims-editor]';

    $script = strtr(<<<'JS'
        (() => {
            const scopeSelector = __SCOPE__;
            const repeaterName = __REPEATER__;
            const expectedSummary = __SUMMARY__;
            const scope = document.querySelector(scopeSelector);

            if (! scope) {
                throw new Error(`Evidence scope ${scopeSelector} was not found.`);
            }

            const repeater = scope.querySelector(`[data-evidence-repeater="${repeaterName}"]`);
            const list = repeater?.querySelector(':scope > .fi-fo-repeater-items');
            const items = list
                ? Array.from(list.children).filter((child) => child.classList.contains('fi-fo-repeater-item'))
                : [];
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();
            const index = items.findIndex((item) => {
                const label = item.querySelector(':scope > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label');

                return label && normalize(label.textContent ?? '').includes(expectedSummary);
            });

            if (index < 0) {
                throw new Error(`Evidence item ${repeaterName} containing ${expectedSummary} was not found.`);
            }

            return index;
        })()
        JS, [
        '__SCOPE__' => json_encode($scopeSelector, JSON_THROW_ON_ERROR),
        '__REPEATER__' => json_encode($repeaterName, JSON_THROW_ON_ERROR),
        '__SUMMARY__' => json_encode($summaryFragment, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);

    sourceWorkspaceBrowserDebugCheckpoint("summary-selector:$repeaterName:$summaryFragment:before");
    $index = $page->script($script);
    sourceWorkspaceBrowserDebugCheckpoint("summary-selector:$repeaterName:$summaryFragment:after");

    if (! is_int($index) || $index < 0) {
        throw new RuntimeException("Could not resolve evidence item [$repeaterName] containing [$summaryFragment].");
    }

    return [
        'selector' => sprintf(
            '%s [data-evidence-repeater="%s"] > .fi-fo-repeater-items > .fi-fo-repeater-item:nth-of-type(%d)',
            $scopeSelector,
            $repeaterName,
            $index + 1,
        ),
        'number' => $index + 1,
    ];
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
    sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:resolve-item:before");

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
    sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:resolve-item:after");

    if (! is_string($itemSelector) || $itemSelector === '') {
        throw new RuntimeException("Could not resolve evidence item selector for [$summaryFragment].");
    }

    sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:collapsed-check:before");
    $isCollapsed = $page->script(sprintf(
        'document.querySelector(%s)?.classList.contains("fi-collapsed") ?? false',
        json_encode($itemSelector, JSON_THROW_ON_ERROR),
    ));
    sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:collapsed-check:after");

    if ($isCollapsed !== true) {
        sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:already-expanded");

        return;
    }

    sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:toggle:before");
    toggleSourceWorkspaceEvidenceItem($page, $itemSelector);
    sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:toggle:after");
    $page->assertScript(
        sprintf(
            '!document.querySelector(%s).classList.contains("fi-collapsed")',
            json_encode($itemSelector, JSON_THROW_ON_ERROR),
        ),
        true,
    );
    sourceWorkspaceBrowserDebugCheckpoint("expand:$summaryFragment:expanded");
}

function fillSourceWorkspaceBrowserField(
    AwaitableWebpage $page,
    string $label,
    string $value,
    ?string $scopeSelector = null,
): void {
    sourceWorkspaceBrowserDebugCheckpoint("fill:$label:resolve-selector:before");
    $selector = sourceWorkspaceBrowserFieldSelectorByLabel($page, $label, $scopeSelector);
    sourceWorkspaceBrowserDebugCheckpoint("fill:$label:resolve-selector:after");

    sourceWorkspaceBrowserDebugCheckpoint("fill:$label:fill:before");
    $page->fill($selector, $value);
    sourceWorkspaceBrowserDebugCheckpoint("fill:$label:fill:after");
    $page->assertValue($selector, $value);
    sourceWorkspaceBrowserDebugCheckpoint("fill:$label:value-confirmed");
}

function submitSourceWorkspaceBrowserForm(AwaitableWebpage $page): AwaitableWebpage
{
    sourceWorkspaceBrowserDebugCheckpoint('submit:click:before');
    $page->click('form.source-acquisition-form button[type="submit"]');
    sourceWorkspaceBrowserDebugCheckpoint('submit:click:after');

    return $page;
}

it('scopes Mention JSON validation styling and moves it when the failing Mention changes', function (): void {
    sourceWorkspaceBrowserDebugCheckpoint('test:first:start');

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
    $valentinMention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'person_valentin');
    $annaMention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'person_anna');

    toggleSourceWorkspaceEvidenceItem($page, $valentinMention['selector']);
    assertSourceWorkspaceEvidenceItemValidationState($page, $valentinMention['selector'], hasError: false, collapsed: false);
    fillSourceWorkspaceBrowserField(
        $page,
        'Raw source-local data (JSON object)',
        '{"broken":',
        $valentinMention['selector'],
    );

    submitSourceWorkspaceBrowserForm($page);
    sourceWorkspaceBrowserDebugCheckpoint('first-validation:assert-message:before');
    $page->assertSee(sprintf(
        'Wzmianka nr %d (person_valentin) zawiera błąd składni JSON.',
        $valentinMention['number'],
    ));
    sourceWorkspaceBrowserDebugCheckpoint('first-validation:assert-message:after');
    $page->assertVisible('[data-source-workspace-save-errors]');
    sourceWorkspaceBrowserDebugCheckpoint('first-validation:errors-visible');
    $page->assertScript(
        "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
        false,
    );
    sourceWorkspaceBrowserDebugCheckpoint('first-validation:evidence-root-unmarked');
    $page->assertScript(
        "document.querySelector('[data-source-workspace-source-details-region]').classList.contains('source-workspace-error-region')",
        false,
    );
    sourceWorkspaceBrowserDebugCheckpoint('first-validation:source-details-unmarked');
    sourceWorkspaceBrowserDebugScreenshot($page, '02-first-validation');

    assertSourceWorkspaceEvidenceItemValidationState($page, $valentinMention['selector'], hasError: true, collapsed: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $annaMention['selector'], hasError: false, collapsed: true);

    fillSourceWorkspaceBrowserField(
        $page,
        'Raw source-local data (JSON object)',
        '{}',
        $valentinMention['selector'],
    );
    toggleSourceWorkspaceEvidenceItem($page, $annaMention['selector']);
    assertSourceWorkspaceEvidenceItemValidationState($page, $annaMention['selector'], hasError: false, collapsed: false);
    fillSourceWorkspaceBrowserField(
        $page,
        'Raw source-local data (JSON object)',
        '{"also-broken":',
        $annaMention['selector'],
    );
    toggleSourceWorkspaceEvidenceItem($page, $annaMention['selector']);
    assertSourceWorkspaceEvidenceItemValidationState($page, $annaMention['selector'], hasError: false, collapsed: true);

    submitSourceWorkspaceBrowserForm($page);
    sourceWorkspaceBrowserDebugCheckpoint('second-validation:assert-message:before');
    $page->assertSee(sprintf(
        'Wzmianka nr %d (person_anna) zawiera błąd składni JSON.',
        $annaMention['number'],
    ));
    sourceWorkspaceBrowserDebugCheckpoint('second-validation:assert-message:after');
    $page->assertVisible('[data-source-workspace-save-errors]');
    sourceWorkspaceBrowserDebugCheckpoint('second-validation:errors-visible');
    sourceWorkspaceBrowserDebugScreenshot($page, '03-second-validation');

    assertSourceWorkspaceEvidenceItemValidationState($page, $valentinMention['selector'], hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $annaMention['selector'], hasError: true, collapsed: false);

    $page
        ->assertScript(
            "Array.from(document.querySelectorAll('textarea')).some((textarea) => textarea.value === '{\"also-broken\":')",
            true,
        )
        ->assertNoJavaScriptErrors();

    sourceWorkspaceBrowserDebugCheckpoint('test:first:completed');
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
    $renamedMention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'person_subject');
    $otherMention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'person_other');
    $failingClaim = sourceWorkspaceEvidenceItemBySummary($page, 'claims', 'person_subject');
    $validClaim = sourceWorkspaceEvidenceItemBySummary($page, 'claims', 'person_other');

    toggleSourceWorkspaceEvidenceItem($page, $renamedMention['selector']);
    assertSourceWorkspaceEvidenceItemValidationState($page, $renamedMention['selector'], hasError: false, collapsed: false);
    fillSourceWorkspaceBrowserField(
        $page,
        'Local key',
        'person_renamed',
        $renamedMention['selector'],
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee(sprintf(
            'Twierdzenie nr %d odwołuje się do nieistniejącej Wzmianki jako podmiotu.',
            $failingClaim['number'],
        ))
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "document.querySelector('[data-source-workspace-source-details-region]').classList.contains('source-workspace-error-region')",
            false,
        );

    assertSourceWorkspaceEvidenceItemValidationState($page, $renamedMention['selector'], hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $otherMention['selector'], hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $failingClaim['selector'], hasError: true, collapsed: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $validClaim['selector'], hasError: false, collapsed: true);

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
        ->assertSee('Twierdzenie nr 1 w Zdarzeniu nr 1 zawiera nieprawidłowe dane.')
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
            "document.querySelector('[data-source-workspace-source-details-region]').classList.contains('source-workspace-error-region')",
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
