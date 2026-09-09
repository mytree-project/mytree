<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetEvidenceState;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EvidenceStateApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_source_state_can_be_captured_and_reconstructed_from_immutable_revisions(): void
    {
        $source = app(CreateSource::class)->create(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'Birth record']),
        );
        $mention = app(CreateMention::class)->create(
            sourceId: $source->id,
            kind: new MentionKind('person'),
            localKey: 'person-1',
            rawData: new MentionRawData(['descriptor' => 'włościanin']),
        );
        app(CreateClaim::class)->create(
            sourceId: $source->id,
            subjectMentionId: $mention->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
            value: new TextClaimValue('włościanin'),
            qualifiers: new ClaimQualifiers,
            rawText: 'włościanin',
            origin: new ClaimOrigin(ClaimOriginKind::Manual),
            transcriptionCertainty: new ClaimCertainty('certain'),
            interpretationCertainty: new ClaimCertainty('certain'),
        );

        $evidenceState = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $view = app(GetEvidenceState::class)->get($evidenceState->id);

        self::assertCount(1, $view->sourceRevisions);
        self::assertCount(1, $view->mentionRevisions);
        self::assertCount(1, $view->claimRevisions);
        self::assertSame($evidenceState->snapshot->payloadHash, $view->evidenceState->snapshot->payloadHash);
    }
}
