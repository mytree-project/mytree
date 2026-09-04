<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceType;

final readonly class CreateSource
{
    private SourceRepository $repository;

    private SourceIdentifierGenerator $identifiers;

    public function __construct(
        SourceRepository $repository,
        SourceIdentifierGenerator $identifiers,
    ) {
        $this->repository = $repository;
        $this->identifiers = $identifiers;
    }

    /** @param list<SourceTextInput> $texts */
    public function handle(
        SourceType $type,
        ?SourceMetadata $metadata = null,
        array $texts = [],
    ): Source {
        $source = new Source(
            id: $this->identifiers->sourceId(),
            type: $type,
            metadata: $metadata ?? SourceMetadata::empty(),
            texts: $this->makeTexts($texts),
        );

        $this->repository->save($source);

        return $source;
    }

    /**
     * @param  list<SourceTextInput>  $inputs
     * @return list<SourceText>
     */
    private function makeTexts(array $inputs): array
    {
        $texts = [];

        foreach ($inputs as $input) {
            $texts[] = new SourceText(
                id: $input->id ?? $this->identifiers->sourceTextId(),
                kind: $input->kind,
                content: $input->content,
                language: $input->language,
            );
        }

        return $texts;
    }
}
