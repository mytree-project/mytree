<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

use Filament\Forms\Components\Select;

/**
 * Mention-reference selector whose option list is a UX constraint, not the
 * authoritative integrity boundary.
 *
 * A SourceDraft may temporarily contain a stale local-key reference while the
 * user edits a Mention. That state must reach SupportedAcquisitionDraftEditor,
 * which owns existence and Mention-kind validation and reports the targeted
 * workspace error. Filament's automatic `in` validation would otherwise mask
 * that domain/application validation with a generic "selected is invalid"
 * message before the draft can be checked.
 */
final class MentionReferenceSelect extends Select
{
    /** @return ?array<string> */
    public function getInValidationRuleValues(): ?array
    {
        return null;
    }
}
