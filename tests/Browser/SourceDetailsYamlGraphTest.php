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
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

function authenticateSourceDetailsYamlBrowserTestUser(): void
{
    $guardName = config('auth.defaults.guard');

    if (! is_string($guardName) || $guardName === '') {
        throw new RuntimeException('Default authentication guard is not configured.');
    }

    $auth = app(AuthFactory::class);
    $user = User::factory()->admin()->create([
        'email' => 'source-details-yaml-admin@example.test',
    ]);

    $auth->guard($guardName)->setUser($user);
    $auth->shouldUse($guardName);
}

it('loads the persisted evidence YAML lazily in Source details', function (): void {
    app(UpdateApplicationSettings::class)->handle(
        new ApplicationSettings(defaultLocale: 'pl'),
        changedBy: null,
    );

    $source = app(CreateSource::class)->handle(
        SourceType::generic(),
        name: 'Akt urodzenia Piotra Wiśniewskiego',
    );
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_piotr',
        role: 'child',
        displayLabel: 'Piotr Wiśniewski',
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
        value: new TextClaimValue('Piotr'),
    );

    authenticateSourceDetailsYamlBrowserTestUser();

    $page = visit('/admin/acquisition/sources');
    $trigger = sprintf(
        '[data-source-details-trigger="%s"]',
        $source->id->value,
    );

    $page
        ->assertPresent($trigger)
        ->click($trigger)
        ->assertVisible('[data-source-details-panel]')
        ->assertSee('Graf źródła (YAML)')
        ->assertVisible('[data-source-details-yaml-download]')
        ->assertSee('Pobierz graf źródła jako YAML')
        ->assertSee('mytree.source-evidence-graph.v1')
        ->assertSee('person_piotr')
        ->assertSee('Piotr Wiśniewski')
        ->assertSee('person.given_name')
        ->assertSee('Piotr')
        ->assertNoJavaScriptErrors();
});
