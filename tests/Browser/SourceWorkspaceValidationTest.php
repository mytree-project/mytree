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

    $script = strtr(<<<'JS'
        (() => {
            const scopeSelector = __SCOPE__;
            const repeaterName = __REPEATER__;
            const index = __INDEX__;
            const scope = document.querySelector(scopeSelector);

            if (! scope) {
                throw new Error(`Evidence scope ${scopeSelector} was not found.`);
            }

            const repeater = scope.querySelector(`[data-evidence-repeater="${repeaterName}"]`);
            const list = repeater?.querySelector(':scope > .fi-fo-repeater-items');
            const items = list
                ? Array.from(list.children).filter((child) => child.classList.contains('fi-fo-repeater-item'))
                : [];
            const item = items[index];

            if (! item) {
                throw new Error(`Evidence item ${repeaterName} at index ${index} was not found.`);
            }

            if (! item.id) {
                item.id = `source-workspace-evidence-${Math.random().toString(36).slice(2)}`;
            }

            return `#${CSS.escape(item.id)}`;
        })()
        JS, [
        '__SCOPE__' => json_encode($scopeSelector, JSON_THROW_ON_ERROR),
        '__REPEATER__' => json_encode($repeaterName, JSON_THROW_ON_ERROR),
        '__INDEX__' => (string) $index,
    ]);

    sourceWorkspaceBrowserDebugCheckpoint("selector:$repeaterName:$index:before");
    $selector = $page->script($script);
    sourceWorkspaceBrowserDebugCheckpoint("selector:$repeaterName:$index:after");

    if (! is_string($selector) || $selector === '') {
        throw new RuntimeException("Could not resolve evidence item [$repeaterName] at index [$index].");
    }

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

            const item = items[index];
            if (! item.id) {
                item.id = `source-workspace-evidence-${Math.random().toString(36).slice(2)}`;
            }

            return JSON.stringify({
                selector: `#${CSS.escape(item.id)}`,
                number: index + 1,
            });
        })()
        JS, [
        '__SCOPE__' => json_encode($scopeSelector, JSON_THROW_ON_ERROR),
        '__REPEATER__' => json_encode($repeaterName, JSON_THROW_ON_ERROR),
        '__SUMMARY__' => json_encode($summaryFragment, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);

    sourceWorkspaceBrowserDebugCheckpoint("summary-selector:$repeaterName:$summaryFragment:before");
    $result = $page->script($script);
    sourceWorkspaceBrowserDebugCheckpoint("summary-selector:$repeaterName:$summaryFragment:after");

    if (! is_string($result) || $result === '') {
        throw new RuntimeException("Could not resolve evidence item [$repeaterName] containing [$summaryFragment].");
    }

    $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($decoded)
        || ! is_string($decoded['selector'] ?? null)
        || ! is_int($decoded['number'] ?? null)) {
        throw new RuntimeException("Invalid evidence item selector payload for [$repeaterName] containing [$summaryFragment].");
    }

    return [
        'selector' => $decoded['selector'],
        'number' => $decoded['number'],
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
    string $repeaterName = 'mentions',
    ?string $scopeSelector = null,
): void {
    sourceWorkspaceBrowserDebugCheckpoint(
        "expand:$repeaterName:$summaryFragment:resolve-item:before",
    );
    $item = sourceWorkspaceEvidenceItemBySummary(
        $page,
        $repeaterName,
        $summaryFragment,
        $scopeSelector,
    );
    $itemSelector = $item['selector'];
    sourceWorkspaceBrowserDebugCheckpoint(
        "expand:$repeaterName:$summaryFragment:resolve-item:after",
    );

    sourceWorkspaceBrowserDebugCheckpoint(
        "expand:$repeaterName:$summaryFragment:collapsed-check:before",
    );
    $isCollapsed = $page->script(sprintf(
        'document.querySelector(%s)?.classList.contains("fi-collapsed") ?? false',
        json_encode($itemSelector, JSON_THROW_ON_ERROR),
    ));
    sourceWorkspaceBrowserDebugCheckpoint(
        "expand:$repeaterName:$summaryFragment:collapsed-check:after",
    );

    if ($isCollapsed !== true) {
        sourceWorkspaceBrowserDebugCheckpoint(
            "expand:$repeaterName:$summaryFragment:already-expanded",
        );

        return;
    }

    sourceWorkspaceBrowserDebugCheckpoint(
        "expand:$repeaterName:$summaryFragment:toggle:before",
    );
    toggleSourceWorkspaceEvidenceItem($page, $itemSelector);
    sourceWorkspaceBrowserDebugCheckpoint(
        "expand:$repeaterName:$summaryFragment:toggle:after",
    );
    $page->assertScript(
        sprintf(
            '!document.querySelector(%s).classList.contains("fi-collapsed")',
            json_encode($itemSelector, JSON_THROW_ON_ERROR),
        ),
        true,
    );
    sourceWorkspaceBrowserDebugCheckpoint(
        "expand:$repeaterName:$summaryFragment:expanded",
    );
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

    $fieldState = $page->script(strtr(<<<'JS'
        (() => {
            const selector = __SELECTOR__;
            const control = document.querySelector(selector);

            if (! control) {
                return { present: false };
            }

            const rect = control.getBoundingClientRect();
            const style = window.getComputedStyle(control);

            return {
                present: true,
                disabled: Boolean(control.disabled),
                readOnly: Boolean(control.readOnly),
                display: style.display,
                visibility: style.visibility,
                width: rect.width,
                height: rect.height,
                offsetParent: control.offsetParent !== null,
            };
        })()
        JS, [
        '__SELECTOR__' => json_encode($selector, JSON_THROW_ON_ERROR),
    ]));
    sourceWorkspaceBrowserDebugCheckpoint(
        "fill:$label:control-state:".json_encode($fieldState, JSON_THROW_ON_ERROR),
    );

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

it('keeps a nested Claim attached when its containing Mention local key is renamed', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_subject',
        displayLabel: 'Subject person',
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
        value: new TextClaimValue('rolnik'),
    );

    authenticateSourceWorkspaceBrowserTestUser();

    $page = openSourceWorkspaceForBrowserTest($source->id->value);
    $mention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'person_subject');

    toggleSourceWorkspaceEvidenceItem($page, $mention['selector']);
    assertSourceWorkspaceEvidenceItemValidationState($page, $mention['selector'], hasError: false, collapsed: false);

    $claim = sourceWorkspaceEvidenceItemSelector($page, 'claims', 0, $mention['selector']);
    assertSourceWorkspaceEvidenceItemValidationState($page, $claim, hasError: false, collapsed: true);

    fillSourceWorkspaceBrowserField(
        $page,
        'Local key',
        'person_renamed',
        $mention['selector'],
    );

    submitSourceWorkspaceBrowserForm($page);

    $page
        ->assertDontSee('odwołuje się do nieistniejącej Wzmianki jako podmiotu')
        ->assertDontSee('Subject Mention local key')
        ->assertNoJavaScriptErrors();
});

it('marks a failing nested event Claim while treating the Event as an ordinary Mention', function (): void {
    sourceWorkspaceBrowserDebugCheckpoint('test:event-claim:start');

    $source = app(CreateSource::class)->handle(SourceType::generic());
    sourceWorkspaceBrowserDebugCheckpoint('test:event-claim:source-created');
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
    $placeMention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'place_original');
    $eventMention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'event_birth');

    expandSourceWorkspaceEvidenceItem($page, 'event_birth');
    $eventClaim = sourceWorkspaceEvidenceItemSelector(
        $page,
        'claims',
        0,
        $eventMention['selector'],
    );

    expandSourceWorkspaceEvidenceItem($page, 'place_original');
    sourceWorkspaceBrowserDebugCheckpoint('place-after-expand:resolve-fresh:before');
    $placeMention = sourceWorkspaceEvidenceItemBySummary($page, 'mentions', 'place_original');
    sourceWorkspaceBrowserDebugCheckpoint('place-after-expand:resolve-fresh:after');

    fillSourceWorkspaceBrowserField(
        $page,
        'Local key',
        'place_renamed',
        $placeMention['selector'],
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Twierdzenie nr 1 odwołuje się do nieistniejącej Wzmianki jako obiektu.')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            false,
        );

    assertSourceWorkspaceEvidenceItemValidationState($page, $placeMention['selector'], hasError: false);
    assertSourceWorkspaceEvidenceItemValidationState($page, $eventMention['selector'], hasError: false, collapsed: false);
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
