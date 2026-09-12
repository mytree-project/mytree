<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

enum SupportedAcquisitionFieldEditorKind: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Date = 'date';
    case Age = 'age';
    case Boolean = 'boolean';
    case Enum = 'enum';
    case MentionReference = 'mention_reference';
    case EventContext = 'event_context';
}
