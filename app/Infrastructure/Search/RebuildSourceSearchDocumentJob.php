<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\RebuildSourceSearchDocument;
use App\Domain\Acquisition\SourceId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RebuildSourceSearchDocumentJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $sourceId,
    ) {}

    public function uniqueId(): string
    {
        return $this->sourceId;
    }

    public function handle(RebuildSourceSearchDocument $rebuild): void
    {
        $rebuild->handle(new SourceId($this->sourceId));
    }
}
