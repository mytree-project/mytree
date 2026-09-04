<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum SourceTextKind: string
{
    case Transcription = 'transcription';
    case Translation = 'translation';
    case Summary = 'summary';
    case ResearchNote = 'research_note';
}
