<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchIndexNameProcessingProfile;
use App\Application\Search\SearchNameInput;
use App\Application\Search\SearchNameProcessor;
use App\Application\Search\SearchNameType;
use MyTree\NameProcessing\Contracts\NameFolderInterface;
use MyTree\NameProcessing\Contracts\NameNormalizerInterface;
use MyTree\NameProcessing\Contracts\ProfileRepositoryInterface;
use MyTree\NameProcessing\Contracts\TransliteratorInterface;
use MyTree\NameProcessing\Domain\NameInput;
use MyTree\NameProcessing\Domain\NameType;

final readonly class NameProcessingSearchNameProcessor implements SearchNameProcessor
{
    public function __construct(
        private NameNormalizerInterface $normalizer,
        private TransliteratorInterface $transliterator,
        private NameFolderInterface $folder,
        private ProfileRepositoryInterface $profiles,
        private string $packageReference,
    ) {}

    public function indexForms(
        SearchNameInput $input,
        SearchIndexNameProcessingProfile $profile,
    ): array {
        $packageInput = $this->packageInput($input);
        $normalized = $this->normalizer
            ->normalize($packageInput, $profile->normalizeProfile)
            ->firstValue();

        $transliterated = $this->transliterator
            ->transliterate(
                $this->packageInput($input, $normalized),
                $profile->transliterateProfile,
            )
            ->firstValue();

        $foldedNormalized = $this->folder
            ->fold(
                $this->packageInput($input, $normalized),
                $profile->foldProfile,
            )
            ->firstValue();

        $foldedTransliterated = $this->folder
            ->fold(
                $this->packageInput($input, $transliterated, 'Latn'),
                $profile->foldProfile,
            )
            ->firstValue();

        return array_values(array_unique(array_filter(
            [
                $input->value,
                $normalized,
                $transliterated,
                $foldedNormalized,
                $foldedTransliterated,
            ],
            static fn (string $value): bool => trim($value) !== '',
        )));
    }

    public function signature(SearchIndexNameProcessingProfile $profile): string
    {
        $profiles = [
            'normalize' => $this->profiles->get('normalize', $profile->normalizeProfile),
            'transliterate' => $this->profiles->get('transliterate', $profile->transliterateProfile),
            'fold' => $this->profiles->get('fold', $profile->foldProfile),
        ];

        $profileVersions = [];
        foreach ($profiles as $operation => $resolvedProfile) {
            $profileVersions[$operation] = [
                'id' => $resolvedProfile->id,
                'version' => $resolvedProfile->version,
            ];
        }

        return hash('sha256', json_encode([
            'package' => 'mytree/name-processing',
            'package_reference' => $this->packageReference,
            'profiles' => $profileVersions,
            'icu_version' => defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function packageInput(
        SearchNameInput $input,
        ?string $value = null,
        ?string $script = null,
    ): NameInput {
        return new NameInput(
            value: $value ?? $input->value,
            language: $input->language,
            script: $script ?? $input->script,
            type: match ($input->type) {
                SearchNameType::GivenName => NameType::GivenName,
                SearchNameType::Surname => NameType::Surname,
                SearchNameType::PlaceName => NameType::PlaceName,
                SearchNameType::Other => NameType::Other,
            },
        );
    }
}
