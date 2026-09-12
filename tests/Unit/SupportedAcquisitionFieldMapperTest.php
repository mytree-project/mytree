<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\SupportedAcquisitionFieldMapper;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\TextClaimValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SupportedAcquisitionFieldMapperTest extends TestCase
{
    private SupportedAcquisitionFieldMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SupportedAcquisitionFieldMapper(new SupportedAcquisitionFieldCatalog);
    }

    public function test_direct_literal_field_preserves_raw_value_and_effective_time(): void
    {
        $sourceId = new SourceId('00000000-0000-0000-0000-000000000001');
        $person = $this->mention(
            '00000000-0000-0000-0000-000000000002',
            $sourceId,
            MentionKind::person(),
            'person.jan',
        );
        $effectiveTime = DateClaimValue::range(
            '1890–1895',
            HistoricalDate::year(1890),
            HistoricalDate::year(1895),
        );

        $claim = $this->mapper->directClaim(
            fieldKey: PredicateKey::PersonOccupation->value,
            claimId: new ClaimId('00000000-0000-0000-0000-000000000003'),
            sourceId: $sourceId,
            subject: $person,
            value: new TextClaimValue('włościanin'),
            qualifiers: new ClaimQualifiers(effectiveTime: $effectiveTime),
            rawText: 'włościanin ze wsi X',
        );

        self::assertSame(PredicateKey::PersonOccupation, $claim->predicate->key);
        self::assertSame('włościanin', $claim->value?->raw());
        self::assertSame('1890–1895', $claim->qualifiers->effectiveTime?->raw());
        self::assertSame('włościanin ze wsi X', $claim->rawText);
    }

    public function test_mention_reference_field_rejects_cross_source_object(): void
    {
        $sourceId = new SourceId('00000000-0000-0000-0000-000000000011');
        $person = $this->mention(
            '00000000-0000-0000-0000-000000000012',
            $sourceId,
            MentionKind::person(),
            'person.jan',
        );
        $foreignPlace = $this->mention(
            '00000000-0000-0000-0000-000000000013',
            new SourceId('00000000-0000-0000-0000-000000000014'),
            MentionKind::place(),
            'place.krakow',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('object Mention must belong to the edited Source');

        $this->mapper->directClaim(
            fieldKey: PredicateKey::PersonResidence->value,
            claimId: new ClaimId('00000000-0000-0000-0000-000000000015'),
            sourceId: $sourceId,
            subject: $person,
            object: $foreignPlace,
        );
    }

    public function test_reified_event_context_produces_explicit_mention_and_claim_changes(): void
    {
        $sourceId = new SourceId('00000000-0000-0000-0000-000000000021');
        $event = $this->mention(
            '00000000-0000-0000-0000-000000000022',
            $sourceId,
            MentionKind::event(),
            'event.birth.1',
        );
        $person = $this->mention(
            '00000000-0000-0000-0000-000000000023',
            $sourceId,
            MentionKind::person(),
            'person.child',
        );

        $dateClaim = $this->mapper->directClaim(
            fieldKey: PredicateKey::EventDate->value,
            claimId: new ClaimId('00000000-0000-0000-0000-000000000024'),
            sourceId: $sourceId,
            subject: $event,
            value: DateClaimValue::exact('1891-02-03', HistoricalDate::day(1891, 2, 3)),
        );
        $childClaim = $this->mapper->directClaim(
            fieldKey: PredicateKey::EventChild->value,
            claimId: new ClaimId('00000000-0000-0000-0000-000000000025'),
            sourceId: $sourceId,
            subject: $event,
            object: $person,
        );

        $changes = $this->mapper->reifiedEventContext($event, [$dateClaim, $childClaim]);

        self::assertSame([$event], $changes->addMentions);
        self::assertSame([$dateClaim, $childClaim], $changes->addClaims);
    }

    private function mention(string $id, SourceId $sourceId, MentionKind $kind, string $localKey): Mention
    {
        return new Mention(
            id: new MentionId($id),
            sourceId: $sourceId,
            kind: $kind,
            localKey: $localKey,
        );
    }
}
