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

    if (! is_string($guardName) || $guardName === '') {
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
    $page = $pendingPage->__call('assertPresent', ['[data-supported-field-picker]']);

    if (! $page instanceof AwaitableWebpage) {
        throw new RuntimeException('Browser visit did not resolve to an awaitable webpage.');
    }

    return $page;
}

function assertSupportedFieldPickerItemVisible(
    AwaitableWebpage $page,
    string $fieldKey,
    bool $visible,
): void {
    $selector = sprintf(
        '[data-supported-field-picker-item=%s]',
        json_encode($fieldKey, JSON_THROW_ON_ERROR),
    );

    $page->assertScript(
        sprintf('document.querySelector(%s)?.offsetParent !== null', json_encode($selector, JSON_THROW_ON_ERROR)),
        $visible,
    );
}

it('groups and filters supported fields and keeps repeatable canonical choices available', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());

    authenticateSupportedFieldPickerBrowserTestUser();
    $page = openSupportedFieldPickerWorkspace($source->id->value);

    $page
        ->assertPresent('[data-supported-field-picker-trigger]')
        ->click('[data-supported-field-picker-trigger]')
        ->assertVisible('[data-supported-field-picker-panel]')
        ->assertSee('Person facts')
        ->assertSee('Event contexts')
        ->assertSee('Event facts')
        ->assertSee('Place facts')
        ->assertSee('Event group')
        ->assertScript(
            "document.querySelector('[data-supported-field-picker-item="event.context"]').classList.contains('supported-field-picker-item-event-context')",
            true,
        );

    $page->fill('[data-supported-field-picker-search]', 'Occupation');
    assertSupportedFieldPickerItemVisible($page, 'person.occupation', true);
    assertSupportedFieldPickerItemVisible($page, 'person.social_estate', false);

    $page->fill('[data-supported-field-picker-search]', 'person.social_estate');
    assertSupportedFieldPickerItemVisible($page, 'person.social_estate', true);
    assertSupportedFieldPickerItemVisible($page, 'person.occupation', false);

    $page->fill('[data-supported-field-picker-search]', '');
    assertSupportedFieldPickerItemVisible($page, 'person.occupation', true);

    $page
        ->click('[data-supported-field-picker-add][data-supported-field-key="person.occupation"]')
        ->assertSee('Claim 1 · Occupation')
        ->click('[data-supported-field-picker-trigger]')
        ->assertVisible('[data-supported-field-picker-panel]');

    assertSupportedFieldPickerItemVisible($page, 'person.occupation', true);

    $page
        ->click('[data-supported-field-picker-add][data-supported-field-key="person.occupation"]')
        ->assertSee('Claim 2 · Occupation')
        ->assertNoJavaScriptErrors();
});
