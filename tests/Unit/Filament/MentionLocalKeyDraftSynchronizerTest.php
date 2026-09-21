<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Pages\Acquisition\Support\MentionLocalKeyDraftSynchronizer;
use PHPUnit\Framework\TestCase;

final class MentionLocalKeyDraftSynchronizerTest extends TestCase
{
    private MentionLocalKeyDraftSynchronizer $synchronizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->synchronizer = new MentionLocalKeyDraftSynchronizer;
    }

    public function test_first_new_mention_gets_m1(): void
    {
        $state = $this->synchronizer->synchronize([
            'new' => $this->mention(localKey: null),
        ], []);

        self::assertSame('M1', $state['new']['local_key']);
    }

    public function test_new_mentions_use_the_smallest_unused_default_without_colliding_with_existing_keys(): void
    {
        $previous = [
            'first' => $this->mention(localKey: 'M1', id: 'persisted-1'),
            'custom' => $this->mention(localKey: 'person_jan'),
            'third' => $this->mention(localKey: 'M3', id: 'persisted-3'),
        ];

        $state = $this->synchronizer->synchronize([
            ...$previous,
            'new-a' => $this->mention(localKey: null),
            'new-b' => $this->mention(localKey: null),
        ], $previous);

        self::assertSame('M2', $state['new-a']['local_key']);
        self::assertSame('M4', $state['new-b']['local_key']);
    }

    public function test_template_presentation_rows_do_not_consume_default_keys(): void
    {
        $state = $this->synchronizer->synchronize([
            'template' => $this->mention(localKey: null, presentationOrigin: 'template'),
            'new' => $this->mention(localKey: null),
        ], []);

        self::assertNull($state['template']['local_key']);
        self::assertSame('M1', $state['new']['local_key']);
    }

    public function test_existing_and_manually_overridden_keys_are_preserved(): void
    {
        $previous = [
            'persisted' => $this->mention(localKey: 'archive_person', id: 'persisted'),
            'unsaved' => $this->mention(localKey: 'M1'),
        ];

        $state = $this->synchronizer->synchronize([
            'persisted' => $previous['persisted'],
            'unsaved' => $this->mention(localKey: 'person_valentin'),
        ], $previous);

        self::assertSame('archive_person', $state['persisted']['local_key']);
        self::assertSame('person_valentin', $state['unsaved']['local_key']);
    }

    public function test_clean_local_key_rename_updates_dependent_object_references(): void
    {
        $previous = [
            'person' => $this->mention(
                localKey: 'M1',
                claims: [[
                    'field_key' => 'person.residence',
                    'object_local_key' => 'M2',
                ]],
            ),
            'place' => $this->mention(localKey: 'M2'),
        ];

        $current = $previous;
        $current['place']['local_key'] = 'place_home';

        $state = $this->synchronizer->synchronize($current, $previous);

        self::assertSame(
            'place_home',
            $state['person']['claims'][0]['object_local_key'],
        );
    }

    public function test_rename_to_a_duplicate_key_does_not_silently_retarget_references(): void
    {
        $previous = [
            'person' => $this->mention(
                localKey: 'M1',
                claims: [[
                    'field_key' => 'person.parent',
                    'object_local_key' => 'M2',
                ]],
            ),
            'target' => $this->mention(localKey: 'M2'),
            'other' => $this->mention(localKey: 'M3'),
        ];

        $current = $previous;
        $current['target']['local_key'] = 'M3';

        $state = $this->synchronizer->synchronize($current, $previous);

        self::assertSame('M2', $state['person']['claims'][0]['object_local_key']);
    }

    public function test_multiple_clean_renames_rewrite_references_from_the_original_snapshot_once(): void
    {
        $previous = [
            'event' => $this->mention(
                localKey: 'event',
                claims: [
                    ['field_key' => 'event.participant', 'object_local_key' => 'M1'],
                    ['field_key' => 'event.participant', 'object_local_key' => 'M2'],
                ],
            ),
            'first' => $this->mention(localKey: 'M1'),
            'second' => $this->mention(localKey: 'M2'),
        ];

        $current = $previous;
        $current['first']['local_key'] = 'M2';
        $current['second']['local_key'] = 'M3';

        $state = $this->synchronizer->synchronize($current, $previous);

        self::assertSame('M2', $state['event']['claims'][0]['object_local_key']);
        self::assertSame('M3', $state['event']['claims'][1]['object_local_key']);
    }

    /**
     * @param  list<array<string, mixed>>  $claims
     * @return array<string, mixed>
     */
    private function mention(
        ?string $localKey,
        ?string $id = null,
        ?string $presentationOrigin = null,
        array $claims = [],
    ): array {
        return [
            'id' => $id,
            'presentation_origin' => $presentationOrigin,
            'kind' => 'person',
            'local_key' => $localKey,
            'role' => null,
            'display_label' => null,
            'raw_data_json' => null,
            'claims' => $claims,
        ];
    }
}
