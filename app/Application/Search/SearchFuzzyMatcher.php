<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchFuzzyMatcher
{
    private const MAX_LENGTH = 64;

    private const MIN_LENGTH = 4;

    /** @var list<string> */
    private const FIELDS = ['person.given_name', 'person.surname', 'place.name'];

    public function match(
        SearchQueryTerm $term,
        DirectSearchCandidate $candidate,
        float $threshold,
    ): ?SearchFuzzyMatch {
        if ($candidate->origin === SearchValueOrigin::SourceMetadata || ! in_array($candidate->field, self::FIELDS, true)) {
            return null;
        }

        $best = null;

        foreach ($term->comparisonForms as $queryForm) {
            $queryLetters = $this->letters($queryForm);
            if ($queryLetters === null) {
                continue;
            }

            foreach ($candidate->indexForms as $indexedForm) {
                $candidateLetters = $this->letters($indexedForm);
                if ($candidateLetters === null || $queryLetters === $candidateLetters) {
                    continue;
                }

                $longest = max(count($queryLetters), count($candidateLetters));
                if (abs(count($queryLetters) - count($candidateLetters)) / $longest > 1 - $threshold) {
                    continue;
                }

                $similarity = 1 - $this->distance($queryLetters, $candidateLetters) / $longest;

                if ($similarity < $threshold) {
                    continue;
                }

                if ($best !== null && (
                    $similarity < $best->similarity
                    || ($similarity === $best->similarity
                        && strcmp($indexedForm, $best->matchedForm) >= 0)
                )) {
                    continue;
                }

                $best = new SearchFuzzyMatch($queryForm, $indexedForm, $similarity);
            }
        }

        return $best;
    }

    /** @return list<string>|null */
    private function letters(string $value): ?array
    {
        $length = mb_strlen($value);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return null;
        }

        return mb_str_split(mb_strtolower($value));
    }

    /** @param list<string> $left
     *  @param list<string> $right
     */
    private function distance(array $left, array $right): int
    {
        $previous = range(0, count($right));

        foreach ($left as $i => $leftCharacter) {
            $current = [$i + 1];
            foreach ($right as $j => $rightCharacter) {
                $current[$j + 1] = min(
                    $current[$j] + 1,
                    $previous[$j + 1] + 1,
                    $previous[$j] + ($leftCharacter === $rightCharacter ? 0 : 1),
                );
            }

            $previous = $current;
        }

        return $previous[count($right)];
    }
}
