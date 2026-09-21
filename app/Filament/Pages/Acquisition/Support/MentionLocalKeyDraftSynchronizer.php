<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

/**
 * Keeps the editable source-local Mention keys convenient without changing
 * their domain semantics.
 *
 * Repeater item keys are presentation identities. They let us distinguish a
 * newly added Mention from an existing Mention whose local key was edited.
 */
final class MentionLocalKeyDraftSynchronizer
{
    /**
     * @param  array<array-key, mixed>  $current
     * @param  array<array-key, mixed>  $previous
     * @return array<array-key, mixed>
     */
    public function synchronize(array $current, array $previous): array
    {
        $current = $this->assignDefaultsToNewRows($current, $previous);

        $renames = $this->renames($current, $previous);
        if ($renames === []) {
            return $current;
        }

        foreach ($current as $itemKey => $mention) {
            if (! is_array($mention)) {
                continue;
            }

            $claims = $mention['claims'] ?? null;
            if (! is_array($claims)) {
                continue;
            }

            foreach ($claims as $claimKey => $claim) {
                if (! is_array($claim)) {
                    continue;
                }

                $objectLocalKey = $this->localKey($claim['object_local_key'] ?? null);
                if ($objectLocalKey === null || ! isset($renames[$objectLocalKey])) {
                    continue;
                }

                $claim['object_local_key'] = $renames[$objectLocalKey];
                $claims[$claimKey] = $claim;
            }

            $mention['claims'] = $claims;
            $current[$itemKey] = $mention;
        }

        return $current;
    }

    /**
     * @param  array<array-key, mixed>  $current
     * @param  array<array-key, mixed>  $previous
     * @return array<array-key, mixed>
     */
    private function assignDefaultsToNewRows(array $current, array $previous): array
    {
        $used = [];
        foreach ($current as $mention) {
            if (! is_array($mention)) {
                continue;
            }

            $localKey = $this->localKey($mention['local_key'] ?? null);
            if ($localKey !== null) {
                $used[$localKey] = true;
            }
        }

        foreach ($current as $itemKey => $mention) {
            if (array_key_exists($itemKey, $previous)
                || ! is_array($mention)
                || ($mention['presentation_origin'] ?? null) !== null
                || $this->localKey($mention['local_key'] ?? null) !== null) {
                continue;
            }

            $localKey = $this->nextDefaultLocalKey($used);
            $mention['local_key'] = $localKey;
            $current[$itemKey] = $mention;
            $used[$localKey] = true;
        }

        return $current;
    }

    /**
     * @param  array<string, true>  $used
     */
    private function nextDefaultLocalKey(array $used): string
    {
        for ($number = 1; ; $number++) {
            $candidate = 'M'.$number;

            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $current
     * @param  array<array-key, mixed>  $previous
     * @return array<string, string>
     */
    private function renames(array $current, array $previous): array
    {
        $currentCounts = $this->localKeyCounts($current);
        $previousCounts = $this->localKeyCounts($previous);
        $renames = [];

        foreach ($previous as $itemKey => $previousMention) {
            $currentMention = $current[$itemKey] ?? null;
            if (! is_array($previousMention) || ! is_array($currentMention)) {
                continue;
            }

            $previousLocalKey = $this->localKey($previousMention['local_key'] ?? null);
            $currentLocalKey = $this->localKey($currentMention['local_key'] ?? null);

            if ($previousLocalKey === null
                || $currentLocalKey === null
                || $previousLocalKey === $currentLocalKey
                || ($previousCounts[$previousLocalKey] ?? 0) !== 1
                || ($currentCounts[$currentLocalKey] ?? 0) !== 1) {
                continue;
            }

            $renames[$previousLocalKey] = $currentLocalKey;
        }

        return $renames;
    }

    /**
     * @param  array<array-key, mixed>  $mentions
     * @return array<string, int>
     */
    private function localKeyCounts(array $mentions): array
    {
        $counts = [];

        foreach ($mentions as $mention) {
            if (! is_array($mention)) {
                continue;
            }

            $localKey = $this->localKey($mention['local_key'] ?? null);
            if ($localKey === null) {
                continue;
            }

            $counts[$localKey] = ($counts[$localKey] ?? 0) + 1;
        }

        return $counts;
    }

    private function localKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
