<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\LoadSourceDraft;
use App\Application\Acquisition\SourceBrowseItem;
use App\Application\Acquisition\SourceEvidenceGraphYamlExporter;
use App\Application\Acquisition\SourceNotFound;
use App\Domain\Acquisition\SourceId;
use App\Filament\Support\SourceTypePresentationCatalog;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Sources extends Page
{
    protected static ?string $slug = 'acquisition/sources';

    protected string $view = 'filament.pages.acquisition.sources';

    public string $search = '';

    public static function getNavigationLabel(): string
    {
        return __('ui.navigation.source_acquisition');
    }

    public function getTitle(): string
    {
        return __('ui.sources.title');
    }

    /** @return list<SourceBrowseItem> */
    public function sources(): array
    {
        return app(BrowseSources::class)->search($this->search);
    }

    public function sourceTypeLabel(SourceBrowseItem $source): string
    {
        return app(SourceTypePresentationCatalog::class)->display($source->type);
    }

    public function sourceTypeDiagnostic(SourceBrowseItem $source): string
    {
        return app(SourceTypePresentationCatalog::class)->diagnostic($source->type);
    }

    public function metadataSummary(SourceBrowseItem $source): string
    {
        $metadata = $source->metadata->toArray();
        if ($metadata === []) {
            return __('ui.sources.no_metadata');
        }

        $parts = [];
        foreach ($metadata as $key => $value) {
            $parts[] = sprintf(
                '%s: %s',
                str_replace('_', ' ', $key),
                $this->metadataValueSummary($value),
            );
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array{id: string, name: string, type: string, type_diagnostic: string, revision: int, metadata: string, graph_yaml: string}
     */
    public function loadSourceDetails(string $sourceId): array
    {
        $id = new SourceId($sourceId);
        $source = app(BrowseSources::class)->find($id);

        if ($source === null) {
            throw SourceNotFound::forId($id);
        }

        return [
            ...$this->sourceDetails($source),
            'graph_yaml' => $this->sourceEvidenceGraphYaml($id),
        ];
    }

    public function downloadSourceDetailsGraph(string $sourceId): StreamedResponse
    {
        $id = new SourceId($sourceId);
        $yaml = $this->sourceEvidenceGraphYaml($id);

        return response()->streamDownload(
            static function () use ($yaml): void {
                echo $yaml;
            },
            sprintf('source-%s-evidence-graph.yml', $id->value),
            ['Content-Type' => 'application/yaml; charset=UTF-8'],
        );
    }

    private function sourceEvidenceGraphYaml(SourceId $sourceId): string
    {
        $draft = app(LoadSourceDraft::class)->handle($sourceId);

        return app(SourceEvidenceGraphYamlExporter::class)->export(
            $draft->current,
            $draft->current,
        );
    }

    /**
     * @return array{id: string, name: string, type: string, type_diagnostic: string, revision: int, metadata: string}
     */
    private function sourceDetails(SourceBrowseItem $source): array
    {
        return [
            'id' => $source->id->value,
            'name' => $source->name ?? __('ui.sources.untitled'),
            'type' => $this->sourceTypeLabel($source),
            'type_diagnostic' => $this->sourceTypeDiagnostic($source),
            'revision' => $source->revisionNumber,
            'metadata' => $this->metadataSummary($source),
        ];
    }

    private function metadataValueSummary(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_array($value) => sprintf('[%d items]', count($value)),
            default => 'unsupported value',
        };
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('ui.sources.create'))
                ->url(SourceEditor::getUrl()),
        ];
    }
}
