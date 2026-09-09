<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum SourceLocatorType: string
{
    case PdfPage = 'pdf_page';
    case ImageBoundingBox = 'image_bounding_box';
    case MediaTimestamp = 'media_timestamp';
    case QuotedFragment = 'quoted_fragment';
}
