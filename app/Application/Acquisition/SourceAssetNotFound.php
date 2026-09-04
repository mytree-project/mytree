<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAssetId;
use RuntimeException;

final class SourceAssetNotFound extends RuntimeException
{
    public static function forId(SourceAssetId $id): self
    {
        return new self(sprintf('Source asset "%s" was not found.', $id->value));
    }
}
