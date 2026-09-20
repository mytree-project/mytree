<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use InvalidArgumentException;
use JsonException;

final readonly class SourceEvidenceGraphYamlExporter
{
    public function __construct(private SourceEvidenceGraphProjector $projector) {}

    public function export(SourceDraftState $state, ?SourceDraftState $persistedState = null): string
    {
        return "---\n".$this->encode($this->projector->project($state, $persistedState))."\n";
    }

    private function encode(mixed $value, int $indent = 0): string
    {
        if (! is_array($value)) {
            return $this->scalar($value);
        }

        if ($value === []) {
            return '[]';
        }

        return array_is_list($value)
            ? $this->list($value, $indent)
            : $this->map($value, $indent);
    }

    /** @param list<mixed> $values */
    private function list(array $values, int $indent): string
    {
        $lines = [];
        foreach ($values as $value) {
            $prefix = str_repeat(' ', $indent).'-';
            if (is_array($value) && $value !== []) {
                $lines[] = $prefix;
                $lines[] = $this->encode($value, $indent + 2);
            } else {
                $lines[] = $prefix.' '.$this->encode($value, $indent + 2);
            }
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $values */
    private function map(array $values, int $indent): string
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $prefix = str_repeat(' ', $indent).$this->key($key).':';
            if (is_array($value) && $value !== []) {
                $lines[] = $prefix;
                $lines[] = $this->encode($value, $indent + 2);
            } else {
                $lines[] = $prefix.' '.$this->encode($value, $indent + 2);
            }
        }

        return implode("\n", $lines);
    }

    private function key(string $value): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/D', $value) === 1) {
            return $value;
        }

        return $this->quoted($value);
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => $this->number($value),
            is_string($value) => $this->quoted($value),
            default => throw new InvalidArgumentException('YAML graph projection contains an unsupported scalar value.'),
        };
    }

    private function number(int|float $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'YAML graph projection contains a non-finite number.',
                previous: $exception,
            );
        }
    }

    private function quoted(string $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'YAML graph projection contains invalid UTF-8 text.',
                previous: $exception,
            );
        }
    }
}
