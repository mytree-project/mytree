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

function authenticateMentionOwnedPickerBrowserUser(): void
{
    $guardName = config('auth.defaults.guard');

    if (! is_string($guardName) || $guardName === '') {
        throw new RuntimeException('Default authentication guard is not configured.');
    }

    $auth = app(AuthFactory::class);
    $user = User::factory()->admin()->create([
        'email' => 'mention-owned-picker-admin@example.test',
    ]);

    $auth->guard($guardName)->setUser($user);
    $auth->shouldUse($guardName);
}

function openMentionOwnedPickerWorkspace(string $sourceId): AwaitableWebpage
{
    $pendingPage = visit('/admin/acquisition/source?source='.urlencode($sourceId));
    $page = $pendingPage->__call('assertPresent', ['[data-mentions-claims-editor]']);

    if (! $page instanceof AwaitableWebpage) {
        throw new RuntimeException('Browser visit did not resolve to an awaitable webpage.');
    }

    return $page;
}

function mentionOwnedPickerItemSelector(AwaitableWebpage $page, string $summary): string
{
    $selector = $page->script(strtr(<<<'JS'
        (() => {
            const expectedSummary = __SUMMARY__;
            const normalize = (value) => value.replace(/\s+/g, ' ').trim();
            const item = Array.from(document.querySelectorAll('.fi-fo-repeater-item')).find((candidate) => {
                const label = candidate.querySelector('.fi-fo-repeater-item-header-label');

                return label && normalize(label.textContent ?? '').includes(expectedSummary);
            });

            if (! item) {
                throw new Error('Repeater item was not found: ' + expectedSummary);
            }

            if (! item.id) {
                item.id = 'mention-owned-picker-item-' + Math.random().toString(36).slice(2);
            }

            return '#' + CSS.escape(item.id);
        })()
        JS, [
        '__SUMMARY__' => json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]));

    if (! is_string($selector) || $selector === '') {
        throw new RuntimeException("Could not resolve repeater item [$summary].");
    }

    return $selector;
}

function expandMentionOwnedPickerItem(AwaitableWebpage $page, string $selector): void
{
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

function assertMentionOwnedPickerGroup(
    AwaitableWebpage $page,
    string $group,
    bool $present,
): void {
    $page->assertScript(strtr(<<<'JS'
        (() => {
            const group = __GROUP__;
            const panel = Array.from(document.querySelectorAll('[data-supported-field-palette-panel]'))
                .find((candidate) => candidate.offsetParent !== null);

            return panel?.querySelector('[data-supported-field-palette-group="' + CSS.escape(group) + '"]') !== null;
        })()
        JS, [
        '__GROUP__' => json_encode($group, JSON_THROW_ON_ERROR),
    ]), $present);
}

function assertMentionOwnedPickerOption(
    AwaitableWebpage $page,
    string $fieldKey,
    bool $present,
): void {
    $page->assertScript(strtr(<<<'JS'
        (() => {
            const fieldKey = __FIELD_KEY__;
            const panel = Array.from(document.querySelectorAll('[data-supported-field-palette-panel]'))
                .find((candidate) => candidate.offsetParent !== null);

            return panel?.querySelector(
                '[data-supported-field-palette-option][data-supported-field-key="' + CSS.escape(fieldKey) + '"]',
            ) !== null;
        })()
        JS, [
        '__FIELD_KEY__' => json_encode($fieldKey, JSON_THROW_ON_ERROR),
    ]), $present);
}

it('filters the nested Claim palette by the containing Mention kind', function (): void {
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

    $event = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::event(),
        localKey: 'event_birth',
        displayLabel: 'Birth of Jan',
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $event->id,
        predicate: PredicateVocabulary::get(PredicateKey::EventDate),
        value: DateClaimValue::exact('1891-02-03', HistoricalDate::day(1891, 2, 3)),
    );

    authenticateMentionOwnedPickerBrowserUser();
    $page = openMentionOwnedPickerWorkspace($source->id->value);

    $personItem = mentionOwnedPickerItemSelector(
        $page,
        'person · Jan Kowalski · person_jan',
    );
    expandMentionOwnedPickerItem($page, $personItem);

    $personClaim = mentionOwnedPickerItemSelector($page, 'Claim 1 · Given name · Jan');
    expandMentionOwnedPickerItem($page, $personClaim);

    $page->click(
        $personClaim.' [data-supported-field-palette-name="field_key"] [data-supported-field-palette-trigger]',
    );

    assertMentionOwnedPickerGroup($page, 'person_general', true);
    assertMentionOwnedPickerGroup($page, 'person_relationships', true);
    assertMentionOwnedPickerGroup($page, 'event_general', false);
    assertMentionOwnedPickerOption($page, PredicateKey::PersonSpouse->value, true);
    assertMentionOwnedPickerOption($page, PredicateKey::EventDate->value, false);

    $page->script(
        "document.activeElement?.blur(); document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));",
    );

    $eventItem = mentionOwnedPickerItemSelector(
        $page,
        'event · Birth of Jan · event_birth',
    );
    expandMentionOwnedPickerItem($page, $eventItem);

    $eventClaim = mentionOwnedPickerItemSelector($page, 'Claim 1 · Event date · 1891-02-03');
    expandMentionOwnedPickerItem($page, $eventClaim);

    $page->click(
        $eventClaim.' [data-supported-field-palette-name="field_key"] [data-supported-field-palette-trigger]',
    );

    assertMentionOwnedPickerGroup($page, 'event_general', true);
    assertMentionOwnedPickerGroup($page, 'event_roles', true);
    assertMentionOwnedPickerGroup($page, 'person_general', false);
    assertMentionOwnedPickerOption($page, PredicateKey::EventSpouse->value, true);
    assertMentionOwnedPickerOption($page, PredicateKey::PersonSpouse->value, false);

    $page->assertNoJavaScriptErrors();
});
