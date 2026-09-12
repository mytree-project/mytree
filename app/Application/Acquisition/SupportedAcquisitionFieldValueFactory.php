<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\AgeExpressionKind;
use App\Domain\Acquisition\AgeUnit;
use App\Domain\Acquisition\BooleanClaimValue;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\DateExpressionKind;
use App\Domain\Acquisition\EnumClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\IntegerClaimValue;
use App\Domain\Acquisition\TextClaimValue;
use InvalidArgumentException;

final class SupportedAcquisitionFieldValueFactory
{
    public function make(
        SupportedAcquisitionFieldDescriptor $descriptor,
        SupportedAcquisitionFieldValueInput $input,
    ): ClaimValue {
        if (! $descriptor->isDirectClaim() || $descriptor->literalValueType === null) {
            throw new InvalidArgumentException(sprintf(
                'Supported acquisition field "%s" does not accept a literal value.',
                $descriptor->key,
            ));
        }

        return match ($descriptor->editorKind) {
            SupportedAcquisitionFieldEditorKind::Text => new TextClaimValue($input->rawValue),
            SupportedAcquisitionFieldEditorKind::Integer => new IntegerClaimValue(
                $input->rawValue,
                $input->integerValue ?? throw new InvalidArgumentException('Integer field requires a parsed integer value.'),
            ),
            SupportedAcquisitionFieldEditorKind::Date => $this->date($input),
            SupportedAcquisitionFieldEditorKind::Age => $this->age($input),
            SupportedAcquisitionFieldEditorKind::Boolean => new BooleanClaimValue(
                $input->rawValue,
                $input->booleanValue ?? throw new InvalidArgumentException('Boolean field requires a parsed boolean value.'),
            ),
            SupportedAcquisitionFieldEditorKind::Enum => new EnumClaimValue(
                $input->rawValue,
                $input->enumKey ?? throw new InvalidArgumentException('Enum field requires a controlled key.'),
            ),
            SupportedAcquisitionFieldEditorKind::MentionReference,
            SupportedAcquisitionFieldEditorKind::EventContext => throw new InvalidArgumentException(sprintf(
                'Supported acquisition field "%s" is not a literal editor.',
                $descriptor->key,
            )),
        };
    }

    public function effectiveTime(SupportedAcquisitionFieldValueInput $input): DateClaimValue
    {
        return $this->date($input);
    }

    private function date(SupportedAcquisitionFieldValueInput $input): DateClaimValue
    {
        $kind = DateExpressionKind::tryFrom((string) $input->expressionKind)
            ?? throw new InvalidArgumentException('Date field requires an expression kind.');
        $from = $this->historicalDate($input->from, 'Date field requires a start date.');

        return match ($kind) {
            DateExpressionKind::Exact => DateClaimValue::exact($input->rawValue, $from),
            DateExpressionKind::Approximate => DateClaimValue::approximate($input->rawValue, $from),
            DateExpressionKind::Uncertain => DateClaimValue::uncertain($input->rawValue, $from),
            DateExpressionKind::Range => DateClaimValue::range(
                $input->rawValue,
                $from,
                $this->historicalDate($input->to, 'Range date field requires an end date.'),
            ),
        };
    }

    private function age(SupportedAcquisitionFieldValueInput $input): AgeClaimValue
    {
        $kind = AgeExpressionKind::tryFrom((string) $input->expressionKind)
            ?? throw new InvalidArgumentException('Age field requires an expression kind.');
        $unit = AgeUnit::tryFrom((string) $input->ageUnit)
            ?? throw new InvalidArgumentException('Age field requires a supported age unit.');
        $from = $this->integer($input->from, 'Age field requires a lower value.');

        return match ($kind) {
            AgeExpressionKind::Exact => AgeClaimValue::exact($input->rawValue, $from, $unit),
            AgeExpressionKind::Approximate => AgeClaimValue::approximate($input->rawValue, $from, $unit),
            AgeExpressionKind::Uncertain => AgeClaimValue::uncertain($input->rawValue, $from, $unit),
            AgeExpressionKind::Range => AgeClaimValue::range(
                $input->rawValue,
                $from,
                $this->integer($input->to, 'Range age field requires an upper value.'),
                $unit,
            ),
        };
    }

    private function historicalDate(string|int|null $value, string $message): HistoricalDate
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($message);
        }

        return HistoricalDate::fromIsoString(trim($value));
    }

    private function integer(string|int|null $value, string $message): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^\d+$/D', trim($value)) !== 1) {
            throw new InvalidArgumentException($message);
        }

        return (int) trim($value);
    }
}
