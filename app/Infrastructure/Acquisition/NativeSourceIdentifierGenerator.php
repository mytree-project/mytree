<?php

declare(strict_types=1);

namespace App\Infrastructure\Acquisition;

use App\Application\Acquisition\SourceIdentifierGenerator;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceTextId;

final class NativeSourceIdentifierGenerator implements SourceIdentifierGenerator
{
    public function sourceId(): SourceId
    {
        return new SourceId($this->uuidV4());
    }

    public function sourceTextId(): SourceTextId
    {
        return new SourceTextId($this->uuidV4());
    }

    public function sourceAssetId(): SourceAssetId
    {
        return new SourceAssetId($this->uuidV4());
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
