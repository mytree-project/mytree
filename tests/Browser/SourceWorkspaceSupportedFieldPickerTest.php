<?php

declare(strict_types=1);

use App\Application\Acquisition\CreateSource;
use App\Domain\Acquisition\SourceType;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Pest\Browser\Api\AwaitableWebpage;

function authenticateSupportedFieldPickerBrowserTestUser(): void
{
    $guardName = config('auth.defaults.guard');

    if (is_string($guardName) === false || $guardName === '') {
        throw new RuntimeException('Default authentication guard is not configured.');
    }

    $auth = app(AuthFactory::class);
    $user = User::factory()->admin()->create([
        'email' => 'supported-field-picker-admin@example.test',
    ]);

    $auth->guard($guardName)->setUser($user);
    $auth->shouldUse($guardName);
}

function openSupportedFieldPickerWorkspace(string $sourceId): AwaitableWebpage
{
    $pendingPage = visit('/admin/acquisition/source?source='.urlencode($sourceId));
    $page = $pendingPage->__call('assertPresent', ['[data-supported-field-palette-name="add_supported_field"]']);

    if (($page instanceof AwaitableWebpage) === false) {
        throw new RuntimeException('Browser visit did not resolve to an awaitable webpage.');
    }

    return $page;
}

function supportedFieldPaletteTrigger(string $name): string
{
    return sprintf(
        '[data-supported-field-palette-name=%s] [data-supported-field-palette-trigger]',
        json_encode($name, JSON_THROW_ON_ERROR),
    );
}

function expandSupportedFieldPickerClaim(
    AwaitableWebpage $page,
    string $summary,
): void {
    $selector = $page->script(strtr(<<<'JS'
        (() => {
            const expectedSummary = __SUMMARY__;
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();
            const item = Array.from(document.querySelectorAll('.fi-fo-repeater-item')).find((candidate) => {
                const label = candidate.querySelector('.fi-fo-repeater-item-header-label');

                return label && normalize(label.textContent ?? '').includes(expectedSummary);
            });

            if (! item) {
                throw new Error('Claim repeater item was not found: ' + expectedSummary);
            }

            if (! item.id) {
                item.id = 'supported-field-picker-claim-' + Math.random().toString(36).slice(2);
            }

            return '#' + CSS.escape(item.id);
        })()
        JS, [
        '__SUMMARY__' => json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]));

    if (is_string($selector) === false || $selector === '') {
        throw new RuntimeException("Could not resolve Claim repeater item [$summary].");
    }

    $collapsed = $page->script(sprintf(
        'document.querySelector(%s)?.classList.contains("fi-collapsed") === true',
        json_encode($selector, JSON_THROW_ON_ERROR),
    ));

    if ($collapsed === true) {
        $page->click(
            $selector.' > .fi-fo-repeater-item-header > .fi-fo-repeater-item-header-end-actions > .fi-fo-repeater-item-header-collapsible-actions',
        );
    }

    $page->assertScript(
        sprintf(
            'document.querySelector(%s)?.classList.contains("fi-collapsed") === false',
            json_encode($selector, JSON_THROW_ON_ERROR),
        ),
        true,
    );
}

function activeSupportedFieldPaletteOptionSelector(AwaitableWebpage $page, string $fieldKey): string
{
    $selector = $page->script(strtr(<<<'JS'
        (() => {
            const fieldKey = __FIELD_KEY__;
            const panel = Array.from(document.querySelectorAll('[data-supported-field-palette-panel]'))
                .find((candidate) => candidate.offsetParent !== null);

            if (! panel) {
                throw new Error('No supported-field palette is open.');
            }

            const option = panel.querySelector(
                '[data-supported-field-palette-option][data-supported-field-key="' + CSS.escape(fieldKey) + '"]',
            );

            if (! option) {
                throw new Error('Supported field is not available in the active palette: ' + fieldKey);
            }

            if (! option.id) {
                option.id = 'supported-field-palette-option-' + Math.random().toString(36).slice(2);
            }

            return '#' + CSS.escape(option.id);
        })()
        JS, [
        '__FIELD_KEY__' => json_encode($fieldKey, JSON_THROW_ON_ERROR),
    ]));

    if (is_string($selector) === false || $selector === '') {
        throw new RuntimeException(sprintf('Could not resolve picker option [%s].', $fieldKey));
    }

    return $selector;
}

function setActiveSupportedFieldPaletteSearch(AwaitableWebpage $page, string $query): void
{
    $page->script(strtr(<<<'JS'
        (() => {
            const query = __QUERY__;
            const panel = Array.from(document.querySelectorAll('[data-supported-field-palette-panel]'))
                .find((candidate) => candidate.offsetParent !== null);
            const input = panel?.querySelector('[data-supported-field-palette-search]');

            if (! (input instanceof HTMLInputElement)) {
                throw new Error('Active supported-field palette search input was not found.');
            }

            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
            setter?.call(input, query);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        })()
        JS, [
        '__QUERY__' => json_encode($query, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]));
}

function assertActiveSupportedFieldPaletteOptionVisible(
    AwaitableWebpage $page,
    string $fieldKey,
    bool $visible,
): void {
    $page->assertScript(strtr(<<<'JS'
        (() => {
            const fieldKey = __FIELD_KEY__;
            const panel = Array.from(document.querySelectorAll('[data-supported-field-palette-panel]'))
                .find((candidate) => candidate.offsetParent !== null);
            const option = panel?.querySelector(
                '[data-supported-field-palette-option][data-supported-field-key="' + CSS.escape(fieldKey) + '"]',
            );

            return option?.offsetParent !== null;
        })()
        JS, [
        '__FIELD_KEY__' => json_encode($fieldKey, JSON_THROW_ON_ERROR),
    ]), $visible);
}

it('opens a multi-column categorized predicate palette and filters by label or canonical key', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());

    authenticateSupportedFieldPickerBrowserTestUser();
    $page = openSupportedFieldPickerWorkspace($source->id->value);

    $page
        ->click(supportedFieldPaletteTrigger('add_supported_field'))
        ->assertVisible('[data-supported-field-palette-panel]')
        ->assertSee('Person · Basic information')
        ->assertSee('Person · Relationships')
        ->assertSee('Person · Places')
        ->assertSee('Person · Occupation, status & titles')
        ->assertSee('Event contexts')
        ->assertSee('Event · General facts')
        ->assertSee('Event · Participants & roles')
        ->assertSee('Place')
        ->assertScript(
            "getComputedStyle(Array.from(document.querySelectorAll('[data-supported-field-palette-panel]')).find((candidate) => candidate.offsetParent !== null).querySelector('.supported-field-palette-columns')).gridTemplateColumns.split(' ').length > 1",
            true,
        );

    $eventContext = activeSupportedFieldPaletteOptionSelector($page, 'event.context');
    $page->assertScript(
        sprintf(
            'document.querySelector(%s).classList.contains("supported-field-palette-option-event-context")',
            json_encode($eventContext, JSON_THROW_ON_ERROR),
        ),
        true,
    );

    setActiveSupportedFieldPaletteSearch($page, 'Occupation');
    assertActiveSupportedFieldPaletteOptionVisible($page, 'person.occupation', true);
    assertActiveSupportedFieldPaletteOptionVisible($page, 'person.social_estate', false);

    setActiveSupportedFieldPaletteSearch($page, 'person.social_estate');
    assertActiveSupportedFieldPaletteOptionVisible($page, 'person.social_estate', true);
    assertActiveSupportedFieldPaletteOptionVisible($page, 'person.occupation', false);

    setActiveSupportedFieldPaletteSearch($page, '');
    $occupation = activeSupportedFieldPaletteOptionSelector($page, 'person.occupation');

    $page
        ->click($occupation)
        ->assertSee('Claim 1 · Occupation');

    expandSupportedFieldPickerClaim($page, 'Claim 1 · Occupation');

    $page
        ->assertVisible(supportedFieldPaletteTrigger('field_key'))
        ->click(supportedFieldPaletteTrigger('field_key'))
        ->assertVisible('[data-supported-field-palette-panel]');

    $page->assertScript(
        "(() => { const panel = Array.from(document.querySelectorAll('[data-supported-field-palette-panel]')).find((candidate) => candidate.offsetParent !== null); return panel.querySelector('[data-supported-field-palette-group=\"event_contexts\"]') === null && panel.querySelector('[data-supported-field-palette-group=\"event_general\"]') === null; })()",
        true,
    );

    $birthDate = activeSupportedFieldPaletteOptionSelector($page, 'person.birth_date');

    $page
        ->click($birthDate)
        ->assertSee('Claim 1 · Birth date')
        ->assertSee('Expression');

    $page
        ->click(supportedFieldPaletteTrigger('add_supported_field'))
        ->assertVisible('[data-supported-field-palette-panel]');

    assertActiveSupportedFieldPaletteOptionVisible($page, 'person.occupation', true);
    $secondOccupation = activeSupportedFieldPaletteOptionSelector($page, 'person.occupation');

    $page
        ->click($secondOccupation)
        ->assertSee('Claim 2 · Occupation')
        ->assertNoJavaScriptErrors();
});
