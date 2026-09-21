<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\CreateSourceTypeTemplate;
use App\Application\Acquisition\GetSource;
use App\Application\Acquisition\GetSourceTypeTemplateVersion;
use App\Application\Acquisition\ListSourceTypeTemplates;
use App\Application\Acquisition\SourceTypeTemplateConflict;
use App\Application\Acquisition\SourceTypeTemplateDefinition;
use App\Application\Acquisition\SourceTypeTemplateVersion;
use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\UpdateSourceTypeTemplate;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class SourceTypeTemplateApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_template_is_a_first_class_versioned_configuration(): void
    {
        $template = app(CreateSourceTypeTemplate::class)->handle(
            new SourceTypeTemplateDefinition(
                name: 'Blank',
                description: 'Start without structured defaults.',
                compatibleSourceTypes: [],
                defaultFieldKeys: [],
            ),
            changedBy: 'admin-1',
        );

        self::assertSame(1, $template->version);
        self::assertSame([], $template->definition->defaultFieldKeys);
        self::assertTrue($template->definition->active);
        self::assertSame('admin-1', $template->changedBy);

        $listed = app(ListSourceTypeTemplates::class)->handle(activeOnly: true);

        self::assertCount(1, $listed);
        self::assertSame($template->templateId->value, $listed[0]->templateId->value);
        self::assertDatabaseCount('source_type_template_versions', 1);
    }

    public function test_template_edits_append_versions_and_preserve_old_versions_and_field_order(): void
    {
        $birth = app(CreateSourceTypeTemplate::class)->handle(
            $this->birthTemplate(),
            changedBy: 'admin-1',
        );

        $updatedDefinition = new SourceTypeTemplateDefinition(
            name: 'Civil birth record',
            description: 'Birth record defaults with the event context first.',
            compatibleSourceTypes: [new SourceType('civil.birth')],
            defaultFieldKeys: [
                SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY,
                PredicateKey::PersonSurname->value,
                PredicateKey::PersonGivenName->value,
                PredicateKey::PersonBirthDate->value,
            ],
        );

        $updated = app(UpdateSourceTypeTemplate::class)->handle(
            templateId: $birth->templateId,
            expectedVersion: 1,
            definition: $updatedDefinition,
            changedBy: 'admin-2',
        );

        self::assertSame(2, $updated->version);
        self::assertSame($updatedDefinition->defaultFieldKeys, $updated->definition->defaultFieldKeys);

        $original = app(GetSourceTypeTemplateVersion::class)->handle($birth->templateId, 1);

        self::assertSame($this->birthTemplate()->defaultFieldKeys, $original->definition->defaultFieldKeys);
        self::assertSame('admin-1', $original->changedBy);
        self::assertDatabaseCount('source_type_template_versions', 2);
    }

    public function test_no_op_save_does_not_create_a_redundant_template_version(): void
    {
        $template = app(CreateSourceTypeTemplate::class)->handle($this->birthTemplate());

        $unchanged = app(UpdateSourceTypeTemplate::class)->handle(
            templateId: $template->templateId,
            expectedVersion: $template->version,
            definition: $this->birthTemplate(),
        );

        self::assertSame(1, $unchanged->version);
        self::assertDatabaseCount('source_type_template_versions', 1);
    }

    public function test_source_type_compatibility_is_only_a_listing_filter(): void
    {
        $blank = app(CreateSourceTypeTemplate::class)->handle(new SourceTypeTemplateDefinition(name: 'Blank'));
        $birth = app(CreateSourceTypeTemplate::class)->handle($this->birthTemplate());
        app(CreateSourceTypeTemplate::class)->handle($this->deathTemplate());

        $birthTemplates = app(ListSourceTypeTemplates::class)->handle(
            activeOnly: true,
            compatibleSourceType: new SourceType('civil.birth'),
        );

        $expectedIds = [$blank->templateId->value, $birth->templateId->value];
        sort($expectedIds, SORT_STRING);

        self::assertSame($expectedIds, $this->sortedTemplateIds($birthTemplates));
    }

    public function test_deactivation_creates_a_new_version_and_removes_template_from_active_listing(): void
    {
        $template = app(CreateSourceTypeTemplate::class)->handle($this->deathTemplate());

        $inactive = app(UpdateSourceTypeTemplate::class)->handle(
            templateId: $template->templateId,
            expectedVersion: 1,
            definition: new SourceTypeTemplateDefinition(
                name: $template->definition->name,
                description: $template->definition->description,
                compatibleSourceTypes: $template->definition->compatibleSourceTypes,
                defaultFieldKeys: $template->definition->defaultFieldKeys,
                active: false,
            ),
        );

        self::assertSame(2, $inactive->version);
        self::assertFalse($inactive->definition->active);
        self::assertCount(0, app(ListSourceTypeTemplates::class)->handle(activeOnly: true));
        self::assertDatabaseCount('source_type_template_versions', 2);
    }

    public function test_unknown_supported_field_key_is_rejected_before_persistence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported acquisition field');

        try {
            app(CreateSourceTypeTemplate::class)->handle(
                new SourceTypeTemplateDefinition(
                    name: 'Invalid',
                    defaultFieldKeys: ['person.not_registered'],
                ),
            );
        } finally {
            self::assertDatabaseCount('source_type_template_versions', 0);
        }
    }

    public function test_stale_template_edit_is_rejected_instead_of_overwriting_newer_configuration(): void
    {
        $template = app(CreateSourceTypeTemplate::class)->handle($this->birthTemplate());

        app(UpdateSourceTypeTemplate::class)->handle(
            templateId: $template->templateId,
            expectedVersion: 1,
            definition: new SourceTypeTemplateDefinition(
                name: 'Civil birth record v2',
                compatibleSourceTypes: [new SourceType('civil.birth')],
                defaultFieldKeys: $template->definition->defaultFieldKeys,
            ),
        );

        $this->expectException(SourceTypeTemplateConflict::class);

        try {
            app(UpdateSourceTypeTemplate::class)->handle(
                templateId: $template->templateId,
                expectedVersion: 1,
                definition: new SourceTypeTemplateDefinition(
                    name: 'Stale edit',
                    compatibleSourceTypes: [new SourceType('civil.birth')],
                    defaultFieldKeys: $template->definition->defaultFieldKeys,
                ),
            );
        } finally {
            self::assertDatabaseCount('source_type_template_versions', 2);
        }
    }

    public function test_template_changes_do_not_mutate_existing_source_truth_or_history(): void
    {
        $source = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata([
                'archive_reference' => 'A-42',
                'volume' => '7',
            ]),
        );
        $template = app(CreateSourceTypeTemplate::class)->handle($this->birthTemplate());

        app(UpdateSourceTypeTemplate::class)->handle(
            templateId: $template->templateId,
            expectedVersion: 1,
            definition: new SourceTypeTemplateDefinition(
                name: 'Civil birth record',
                compatibleSourceTypes: [new SourceType('civil.birth')],
                defaultFieldKeys: [PredicateKey::PersonSurname->value],
                active: false,
            ),
        );

        $reloaded = app(GetSource::class)->handle($source->id);

        self::assertSame('civil.birth', $reloaded->type->key);
        self::assertSame([
            'archive_reference' => 'A-42',
            'volume' => '7',
        ], $reloaded->metadata->toArray());
        self::assertDatabaseCount('sources', 1);
        self::assertDatabaseCount('source_revisions', 1);
        self::assertDatabaseCount('source_type_template_versions', 2);
    }

    private function birthTemplate(): SourceTypeTemplateDefinition
    {
        return new SourceTypeTemplateDefinition(
            name: 'Civil birth record',
            description: 'Representative birth-record defaults.',
            compatibleSourceTypes: [new SourceType('civil.birth')],
            defaultFieldKeys: [
                PredicateKey::PersonGivenName->value,
                PredicateKey::PersonSurname->value,
                PredicateKey::PersonBirthDate->value,
                SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY,
            ],
        );
    }

    private function deathTemplate(): SourceTypeTemplateDefinition
    {
        return new SourceTypeTemplateDefinition(
            name: 'Civil death record',
            description: 'Representative death-record defaults.',
            compatibleSourceTypes: [new SourceType('civil.death')],
            defaultFieldKeys: [
                PredicateKey::PersonGivenName->value,
                PredicateKey::PersonSurname->value,
                PredicateKey::PersonDeathDate->value,
                SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY,
            ],
        );
    }

    /**
     * @param  list<SourceTypeTemplateVersion>  $templates
     * @return list<string>
     */
    private function sortedTemplateIds(array $templates): array
    {
        $ids = array_map(
            static fn (SourceTypeTemplateVersion $template): string => $template->templateId->value,
            $templates,
        );
        sort($ids, SORT_STRING);

        return $ids;
    }
    public function test_templates_can_reference_source_recorded_sex_and_religious_affiliation_fields(): void
    {
        $version = app(CreateSourceTypeTemplate::class)->handle(new SourceTypeTemplateDefinition(
            name: 'Birth record with source-recorded person facts',
            compatibleSourceTypes: [SourceType::generic()],
            defaultFieldKeys: [
                PredicateKey::PersonSex->value,
                PredicateKey::PersonReligiousAffiliation->value,
            ],
        ));

        self::assertSame(
            [PredicateKey::PersonSex->value, PredicateKey::PersonReligiousAffiliation->value],
            $version->definition->defaultFieldKeys,
        );
    }

}
