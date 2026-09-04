<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceType;

final readonly class UpdateSource
{
    public function __construct(
        private SourceRepository $repository,
        private SourceIdentifierGenerator $identifiers,
    ) {}

    /**
     * @param list<SourceTextInput> $texts
     */
    public function handle(
        SourceId $id,
        SourceType $type,
        SourceMetadata $metadata,
        array $texts = [],
    ): Source {
        if ($this->repository->find($id) === null) {
            throw SourceNotFound::forId($id);
        }

        $source = new Source(
            id: $id,
            type: $type,
            metadata: $metadata,
            texts: $this->makeTexts($texts),
        );

        $this->repository->save($source);

        return $source;
    }

    /**
     * @param list<SourceTextInput> $inputs
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
