<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Acquisition\SupportedAcquisitionFieldDescriptor;
use App\Application\Acquisition\SupportedAcquisitionFieldEditorKind;
use App\Application\Acquisition\SupportedAcquisitionFieldMappingKind;
use App\Application\Acquisition\SupportedAcquisitionFieldValueFactory;
use App\Application\Acquisition\SupportedAcquisitionFieldValueInput;
use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\BooleanClaimValue;
use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\EnumClaimValue;
use App\Domain\Acquisition\IntegerClaimValue;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupportedAcquisitionFieldValueFactoryTest extends TestCase
{
    private SupportedAcquisitionFieldValueFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SupportedAcquisitionFieldValueFactory;
    }

    public function test_date_range_preserves_raw_wording_and_supported_precision(): void
    {
        $value = $this->factory->make(
            $this->descriptor(SupportedAcquisitionFieldEditorKind::Date, ClaimValueType::Date),
            new SupportedAcquisitionFieldValueInput(
                rawValue: 'między 1890 a marcem 1892',
                expressionKind: 'range',
                from: '1890',
                to: '1892-03',
            ),
        );

        self::assertInstanceOf(DateClaimValue::class, $value);
        self::assertSame('między 1890 a marcem 1892', $value->raw());
        self::assertSame('1890', $value->from->toIsoString());
        self::assertSame('1892-03', $value->to?->toIsoString());
    }

    public function test_uncertain_age_preserves_raw_wording_and_unit(): void
    {
        $value = $this->factory->make(
            $this->descriptor(SupportedAcquisitionFieldEditorKind::Age, ClaimValueType::Age),
            new SupportedAcquisitionFieldValueInput(
                rawValue: 'około 18 miesięcy',
                expressionKind: 'uncertain',
                from: 18,
                ageUnit: 'months',
            ),
        );

        self::assertInstanceOf(AgeClaimValue::class, $value);
        self::assertSame('około 18 miesięcy', $value->raw());
        self::assertSame(18, $value->from);
        self::assertSame('months', $value->unit->value);
    }

    /**
     * @param  class-string  $expectedClass
     */
    #[DataProvider('scalarValueProvider')]
    public function test_scalar_typed_value_adapters_round_trip(
        SupportedAcquisitionFieldEditorKind $editorKind,
        ClaimValueType $valueType,
        SupportedAcquisitionFieldValueInput $input,
        string $expectedClass,
        mixed $expectedParsedValue,
    ): void {
        $value = $this->factory->make($this->descriptor($editorKind, $valueType), $input);

        self::assertInstanceOf($expectedClass, $value);
        self::assertSame($input->rawValue, $value->raw());

        $data = $value->data();
        $parsed = match ($valueType) {
            ClaimValueType::Integer, ClaimValueType::Boolean => $data['value'] ?? null,
            ClaimValueType::Enum => $data['key'] ?? null,
            default => null,
        };
        self::assertSame($expectedParsedValue, $parsed);
    }

    /**
     * @return iterable<string, array{SupportedAcquisitionFieldEditorKind, ClaimValueType, SupportedAcquisitionFieldValueInput, class-string, mixed}>
     */
    public static function scalarValueProvider(): iterable
    {
        yield 'integer' => [
            SupportedAcquisitionFieldEditorKind::Integer,
            ClaimValueType::Integer,
            new SupportedAcquisitionFieldValueInput(rawValue: '42', integerValue: 42),
            IntegerClaimValue::class,
            42,
        ];

        yield 'boolean' => [
            SupportedAcquisitionFieldEditorKind::Boolean,
            ClaimValueType::Boolean,
            new SupportedAcquisitionFieldValueInput(rawValue: 'tak', booleanValue: true),
            BooleanClaimValue::class,
            true,
        ];

        yield 'enum' => [
            SupportedAcquisitionFieldEditorKind::Enum,
            ClaimValueType::Enum,
            new SupportedAcquisitionFieldValueInput(rawValue: 'wariant źródłowy', enumKey: 'controlled.value'),
            EnumClaimValue::class,
            'controlled.value',
        ];
    }

    private function descriptor(
        SupportedAcquisitionFieldEditorKind $editorKind,
        ClaimValueType $valueType,
    ): SupportedAcquisitionFieldDescriptor {
        return new SupportedAcquisitionFieldDescriptor(
            key: 'test.typed_value',
            label: 'Typed value',
            group: 'Test',
            editorKind: $editorKind,
            repeatable: true,
            mappingKind: SupportedAcquisitionFieldMappingKind::DirectClaim,
            subjectMentionKind: MentionKind::PERSON,
            predicateKey: PredicateKey::PersonGivenName,
            literalValueType: $valueType,
        );
    }
}
