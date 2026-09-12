<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\CanonicalJson;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimRevisionSnapshot;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\MentionRevisionSnapshot;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceRevisionSnapshot;
use InvalidArgumentException;

final readonly class SourceDraftState
{
    private const SEMANTIC_REVISION_ID = '00000000-0000-0000-0000-000000000000';

    /** @var list<SourceAsset> */
    public array $assets;

    /** @var list<Mention> */
    public array $mentions;

    /** @var list<Claim> */
    public array $claims;

    /** @var list<SourceLocator> */
    public array $locators;

    /**
     * @param  list<SourceAsset>  $assets
     * @param  list<Mention>  $mentions
     * @param  list<Claim>  $claims
     * @param  list<SourceLocator>  $locators
     */
    public function __construct(
        public Source $source,
        array $assets = [],
        array $mentions = [],
        array $claims = [],
        array $locators = [],
    ) {
        $this->assets = self::unique($assets, static fn (SourceAsset $asset): string => $asset->id->value, 'SourceAsset');
        $this->mentions = self::unique($mentions, static fn (Mention $mention): string => $mention->id->value, 'Mention');
        $this->claims = self::unique($claims, static fn (Claim $claim): string => $claim->id->value, 'Claim');
        $this->locators = self::unique($locators, static fn (SourceLocator $locator): string => $locator->id->value, 'SourceLocator');
    }

    public function semanticHash(): string
    {
        $mentionHashes = [];
        foreach ($this->mentions as $mention) {
            $mentionHashes[$mention->id->value] = MentionRevisionSnapshot::capture($mention)->payloadHash;
        }
        ksort($mentionHashes, SORT_STRING);

        $claimHashes = [];
        foreach ($this->claims as $claim) {
            $claimHashes[$claim->id->value] = $this->claimSemanticHash($claim);
        }
        ksort($claimHashes, SORT_STRING);

        $payload = CanonicalJson::encode([
            'source' => $this->sourceSemanticHash(),
            'mentions' => $mentionHashes,
            'claims' => $claimHashes,
        ]);

        return hash('sha256', $payload);
    }

    public function sourceSemanticHash(): string
    {
        return SourceRevisionSnapshot::capture($this->source, $this->assets)->payloadHash;
    }

    public function mentionSemanticHash(Mention $mention): string
    {
        return MentionRevisionSnapshot::capture($mention)->payloadHash;
    }

    public function claimSemanticHash(Claim $claim): string
    {
        return $this->captureClaimSemanticSnapshot($claim, $this->locatorsForClaim($claim->id))->payloadHash;
    }

    public function claimObjectSemanticHash(Claim $claim): string
    {
        return $this->captureClaimSemanticSnapshot($claim, [])->payloadHash;
    }

    /** @return list<SourceLocator> */
    public function locatorsForClaim(ClaimId $claimId): array
    {
        return array_values(array_filter(
            $this->locators,
            static fn (SourceLocator $locator): bool => $locator->claimId->value === $claimId->value,
        ));
    }

    public function mention(MentionId $id): ?Mention
    {
        foreach ($this->mentions as $mention) {
            if ($mention->id->value === $id->value) {
                return $mention;
            }
        }

        return null;
    }

    public function claim(ClaimId $id): ?Claim
    {
        foreach ($this->claims as $claim) {
            if ($claim->id->value === $id->value) {
                return $claim;
            }
        }

        return null;
    }

    /** @param  list<SourceLocator>  $locators */
    private function captureClaimSemanticSnapshot(Claim $claim, array $locators): ClaimRevisionSnapshot
    {
        $revisionId = new MentionRevisionId(self::SEMANTIC_REVISION_ID);

        return ClaimRevisionSnapshot::capture(
            claim: $claim,
            subjectMentionRevisionId: $revisionId,
            objectMentionRevisionId: $claim->objectMentionId === null ? null : $revisionId,
            sourceLocators: $locators,
        );
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $values
     * @param  callable(T): string  $key
     * @return list<T>
     */
    private static function unique(array $values, callable $key, string $type): array
    {
        $seen = [];
        foreach ($values as $value) {
            $id = $key($value);
            if (isset($seen[$id])) {
                throw new InvalidArgumentException(sprintf('%s ids must be unique within a SourceDraft.', $type));
            }
            $seen[$id] = true;
        }

        return $values;
    }
}
