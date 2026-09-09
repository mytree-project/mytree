<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimRevisionSnapshot;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\PdfPageLocatorValue;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\QuotedFragmentLocatorValue;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;
use App\Domain\Acquisition\TextClaimValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClaimRevisionSnapshotTest extends TestCase
{
    public function test_semantically_equivalent_locator_order_produces_identical_payload_and_hash(): void
    {
        $claim = $this->claim();
        $subjectRevisionId = new MentionRevisionId('44444444-4444-4444-8444-444444444444');
        $firstLocator = new SourceLocator(
            id: new SourceLocatorId('66666666-6666-4666-8666-666666666666'),
            sourceId: $claim->sourceId,
            claimId: $claim->id,
            value: new PdfPageLocatorValue(7),
        );
        $secondLocator = new SourceLocator(
            id: new SourceLocatorId('55555555-5555-4555-8555-555555555555'),
            sourceId: $claim->sourceId,
            claimId: $claim->id,
            value: new QuotedFragmentLocatorValue('Jan Kowalski, włościan'),
        );

        $left = ClaimRevisionSnapshot::capture(
            $claim,
            $subjectRevisionId,
            null,
            [$firstLocator, $secondLocator],
        );
        $right = ClaimRevisionSnapshot::capture(
            $claim,
            $subjectRevisionId,
            null,
            [$secondLocator, $firstLocator],
        );

        self::assertSame($left->canonicalPayload, $right->canonicalPayload);
        self::assertSame($left->payloadHash, $right->payloadHash);
    }

    public function test_snapshot_reconstructs_exact_claim_state_and_references(): void
    {
        $claim = $this->claim();
        $subjectRevisionId = new MentionRevisionId('44444444-4444-4444-8444-444444444444');
        $locator = new SourceLocator(
            id: new SourceLocatorId('55555555-5555-4555-8555-555555555555'),
            sourceId: $claim->sourceId,
            claimId: $claim->id,
            value: new QuotedFragmentLocatorValue('Jan Kowalski, włościan'),
        );
        $snapshot = ClaimRevisionSnapshot::capture($claim, $subjectRevisionId, null, [$locator]);

        $state = $snapshot->reconstruct();

        self::assertSame($claim->id->value, $state->claim->id->value);
        self::assertSame('person.occupation', $state->claim->predicate->key->value);
        self::assertSame('włościan', $state->claim->value?->raw());
        self::assertSame('Jan Kowalski, włościan ze wsi X', $state->claim->rawText);
        self::assertSame('provider_observation', $state->claim->origin->kind->value);
        self::assertSame('provider-a', $state->claim->origin->providerKey);
        self::assertSame('certain', $state->claim->transcriptionCertainty->code);
        self::assertSame('probable', $state->claim->interpretationCertainty->code);
        self::assertSame('range', $state->claim->qualifiers->effectiveTime?->kind->value);
        self::assertSame($subjectRevisionId->value, $state->subjectMentionRevisionId->value);
        self::assertNull($state->objectMentionRevisionId);
        self::assertCount(1, $state->sourceLocators);
        self::assertSame('quoted_fragment', $state->sourceLocators[0]->value->type()->value);
    }

    public function test_rehydrate_rejects_hash_mismatch(): void
    {
        $snapshot = ClaimRevisionSnapshot::capture(
            $this->claim(),
            new MentionRevisionId('44444444-4444-4444-8444-444444444444'),
            null,
            [],
        );

        $this->expectException(InvalidArgumentException::class);

        ClaimRevisionSnapshot::rehydrate(
            $snapshot->schemaVersion,
            $snapshot->canonicalPayload,
            str_repeat('0', 64),
        );
    }

    private function claim(): Claim
    {
        return new Claim(
            id: new ClaimId('22222222-2222-4222-8222-222222222222'),
            sourceId: new SourceId('11111111-1111-4111-8111-111111111111'),
            subjectMentionId: new MentionId('33333333-3333-4333-8333-333333333333'),
            predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
            value: new TextClaimValue('włościan'),
            qualifiers: new ClaimQualifiers(
                effectiveTime: DateClaimValue::range(
                    '1890-1895',
                    HistoricalDate::year(1890),
                    HistoricalDate::year(1895),
                ),
            ),
            rawText: 'Jan Kowalski, włościan ze wsi X',
            origin: new ClaimOrigin(
                kind: ClaimOriginKind::ProviderObservation,
                providerKey: 'provider-a',
                providerRecordId: 'A-17',
                requestContext: ['query' => ['surname' => 'Kowalski', 'year' => 1890]],
                metadata: ['language' => 'pl'],
            ),
            transcriptionCertainty: new ClaimCertainty('certain'),
            interpretationCertainty: new ClaimCertainty('probable'),
        );
    }
}
