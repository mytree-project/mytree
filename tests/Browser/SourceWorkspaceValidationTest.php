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
use Pest\Browser\Api\Webpage;
use Tests\TestCase;

function openSourceWorkspaceForBrowserTest(TestCase $test, string $sourceId): Webpage
{
    app(UpdateApplicationSettings::class)->handle(
        new ApplicationSettings(defaultLocale: 'pl'),
        changedBy: null,
    );

    $user = User::factory()->admin()->create([
        'email' => 'browser-admin@example.test',
    ]);

    $test->actingAs($user);

    return visit('/admin/acquisition/source?source='.urlencode($sourceId))
        ->assertPresent('[data-source-workspace]')
        ->assertPresent('[data-mentions-claims-editor]');
}

function sourceWorkspaceBrowserFieldSelectorByLabel(
    Webpage $page,
    string $label,
): string {
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
                control.id = `browser-field-${Math.random().toString(36).slice(2)}`;
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

function fillSourceWorkspaceBrowserField(
    Webpage $page,
    string $label,
    string $value,
): void {
    $selector = sourceWorkspaceBrowserFieldSelectorByLabel($page, $label);

    $page
        ->fill($selector, $value)
        ->assertValue($selector, $value);
}

function submitSourceWorkspaceBrowserForm(Webpage $page): Webpage
{
    return $page->click('form.source-acquisition-form button[type="submit"]');
}

it('routes Mention JSON syntax errors to Mentions and Claims and keeps entered state', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_valentin',
        role: 'declarant',
        displayLabel: 'Valentin Wiśniewski',
    );

    $page = openSourceWorkspaceForBrowserTest($this, $source->id->value);

    fillSourceWorkspaceBrowserField(
        $page,
        'Raw source-local data (JSON object)',
        '{"broken":',
    );

    $page->assertScript(
        "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'person_valentin')",
        true,
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Błąd składni JSON w Mention nr 1 (person_valentin).')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            true,
        )
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'person_valentin')",
            true,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('textarea')).some((textarea) => textarea.value === '{\"broken\":')",
            true,
        )
        ->assertScript("document.querySelector('.source-workspace-error-details').open", false)
        ->click('.source-workspace-error-details summary')
        ->assertVisible('.source-workspace-error-details code')
        ->assertScript("document.querySelector('.source-workspace-error-details code').textContent.trim()", 'Syntax error')
        ->assertNoJavaScriptErrors();
});

it('routes Claim subject errors to Mentions and Claims instead of Source details', function (): void {
    $source = app(CreateSource::class)->handle(SourceType::generic());
    $person = app(CreateMention::class)->handle(
        sourceId: $source->id,
        kind: MentionKind::person(),
        localKey: 'person_subject',
    );
    app(CreateClaim::class)->handle(
        sourceId: $source->id,
        subjectMentionId: $person->id,
        predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
        value: new TextClaimValue('rolnik'),
    );

    $page = openSourceWorkspaceForBrowserTest($this, $source->id->value);

    fillSourceWorkspaceBrowserField(
        $page,
        'Subject Mention local key',
        'missing-person',
    );

    $page->assertScript(
        "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'rolnik')",
        true,
    );

    submitSourceWorkspaceBrowserForm($page)
        ->assertSee('Claim nr 1 odwołuje się do nieistniejącego Mention jako podmiotu.')
        ->assertVisible('[data-source-workspace-save-errors]')
        ->assertScript(
            "document.querySelector('[data-mentions-claims-editor]').classList.contains('source-workspace-error-region')",
            true,
        )
        ->assertScript(
            "document.querySelectorAll('details.source-workspace-details')[0].classList.contains('source-workspace-error-region')",
            false,
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('input')).some((input) => input.value === 'missing-person')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('routes metadata value errors to Source details instead of Mentions and Claims', function (): void {
    $source = app(CreateSource::class)->handle(
        SourceType::generic(),
        metadata: new SourceMetadata(['record_number' => 1]),
    );
    $page = openSourceWorkspaceForBrowserTest($this, $source->id->value);

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
