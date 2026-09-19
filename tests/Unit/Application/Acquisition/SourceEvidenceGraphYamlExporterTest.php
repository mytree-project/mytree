<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Acquisition;

use App\Application\Acquisition\SourceDraftState;
use App\Application\Acquisition\SourceEvidenceGraphProjector;
use App\Application\Acquisition\SourceEvidenceGraphYamlExporter;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use PHPUnit\Framework\TestCase;

final class SourceEvidenceGraphYamlExporterTest extends TestCase
{
    public function test_yaml_projection_is_deterministic_and_keeps_event_contexts_distinct(): void
    {
        $sourceId = new SourceId('11111111-1111-4111-8111-111111111111');
        $source = new Source(
            id: $sourceId,
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata([]),
        );
        $child = new Mention(
            id: new MentionId('22222222-2222-4222-8222-222222222222'),
            sourceId: $sourceId,
            kind: MentionKind::person(),
            localKey: 'person.child',
            role: 'child',
            displayLabel: 'Jan Kowalski',
            rawData: new MentionRawData(['descriptor' => 'włościanin']),
        );
        $place = new Mention(
            id: new MentionId('33333333-3333-4333-8333-333333333333'),
            sourceId: $sourceId,
            kind: MentionKind::place(),
            localKey: 'place.birth',
            displayLabel: 'Wieś X',
        );
        $event = new Mention(
            id: new MentionId('44444444-4444-4444-8444-444444444444'),
            sourceId: $sourceId,
            kind: MentionKind::event(),
            localKey: 'event.birth.1',
            role: 'birth',
            displayLabel: 'Birth record event',
        );

        $givenName = new Claim(
            id: new ClaimId('55555555-5555-4555-8555-555555555555'),
            sourceId: $sourceId,
            subjectMentionId: $child->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Jan'),
        );
        $residence = new Claim(
            id: new ClaimId('66666666-6666-4666-8666-666666666666'),
            sourceId: $sourceId,
            subjectMentionId: $child->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonResidence),
            objectMentionId: $place->id,
        );
        $eventDate = new Claim(
            id: new ClaimId('77777777-7777-4777-8777-777777777777'),
            sourceId: $sourceId,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventDate),
            value: DateClaimValue::exact('3 II 1891', HistoricalDate::fromIsoString('1891-02-03')),
        );
        $eventChild = new Claim(
            id: new ClaimId('88888888-8888-4888-8888-888888888888'),
            sourceId: $sourceId,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventChild),
            objectMentionId: $child->id,
        );

        $state = new SourceDraftState(
            source: $source,
            mentions: [$event, $place, $child],
            claims: [$eventChild, $residence, $givenName, $eventDate],
        );
        $reordered = new SourceDraftState(
            source: $source,
            mentions: [$child, $event, $place],
            claims: [$eventDate, $givenName, $residence, $eventChild],
        );

        $exporter = new SourceEvidenceGraphYamlExporter(new SourceEvidenceGraphProjector);
        $yaml = $exporter->export($state, $state);

        self::assertSame($yaml, $exporter->export($reordered, $state));
        self::assertStringContainsString('schema: "mytree.source-evidence-graph.v1"', $yaml);
        self::assertStringContainsString('local_key: "person.child"', $yaml);
        self::assertStringContainsString('predicate: "person.given_name"', $yaml);
        self::assertStringContainsString('raw: "Jan"', $yaml);
        self::assertStringContainsString('predicate: "person.residence"', $yaml);
        self::assertStringContainsString('local_key: "place.birth"', $yaml);
        self::assertStringContainsString('events:', $yaml);
        self::assertStringContainsString('local_key: "event.birth.1"', $yaml);
        self::assertStringContainsString('raw: "3 II 1891"', $yaml);
        self::assertLessThan(
            strpos($yaml, 'events:'),
            strpos($yaml, 'mentions:'),
        );
    }

    public function test_unpersisted_projection_uses_local_keys_without_ephemeral_ids(): void
    {
        $sourceId = new SourceId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $mentionId = new MentionId('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
        $claimId = new ClaimId('cccccccc-cccc-4ccc-8ccc-cccccccccccc');
        $mention = new Mention(
            id: $mentionId,
            sourceId: $sourceId,
            kind: MentionKind::person(),
            localKey: 'person.unsaved',
        );
        $claim = new Claim(
            id: $claimId,
            sourceId: $sourceId,
            subjectMentionId: $mentionId,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Anna'),
        );
        $state = new SourceDraftState(
            source: new Source(
                id: $sourceId,
                type: SourceType::generic(),
                metadata: new SourceMetadata([]),
            ),
            mentions: [$mention],
            claims: [$claim],
        );

        $yaml = (new SourceEvidenceGraphYamlExporter(new SourceEvidenceGraphProjector))->export($state);

        self::assertStringContainsString('local_key: "person.unsaved"', $yaml);
        self::assertStringNotContainsString($sourceId->value, $yaml);
        self::assertStringNotContainsString($mentionId->value, $yaml);
        self::assertStringNotContainsString($claimId->value, $yaml);
    }
}
