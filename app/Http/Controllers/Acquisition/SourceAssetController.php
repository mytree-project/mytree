<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acquisition;

use App\Application\Acquisition\ReadSourceAsset;
use App\Application\Acquisition\SourceAssetNotFound;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class SourceAssetController
{
    public function __invoke(Request $request, string $source, string $asset, ReadSourceAsset $readSourceAsset): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_admin, 403);

        try {
            $payload = $readSourceAsset->handle(
                new SourceId($source),
                new SourceAssetId($asset),
            );
        } catch (InvalidArgumentException|SourceAssetNotFound) {
            abort(404);
        }

        $disposition = HeaderUtils::makeDisposition(
            'inline',
            $payload->filename,
            'source-asset',
        );

        return response($payload->contents, 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => $disposition,
            'Content-Type' => $payload->mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
