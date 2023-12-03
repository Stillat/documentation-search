<?php

namespace Stillat\DocumentationSearch;

use Illuminate\Support\Collection;
use Statamic\Search\Searchables\Entries;
use Stillat\DocumentationSearch\Contracts\DocumentSplitter;
use Stillat\DocumentationSearch\Contracts\DocumentTransformer;
use Stillat\DocumentationSearch\Document\Splitter;
use Stillat\DocumentationSearch\Document\SplitterConfiguration;
use Stillat\DocumentationSearch\Events\CreatingSearchEntry;
use Stillat\DocumentationSearch\Events\IndexingContent;
use Stillat\DocumentationSearch\Events\SearchEntriesCreated;
use Stillat\DocumentationSearch\Events\StringValue;
use Stillat\DocumentationSearch\Indexing\CreatedSearchEntry;
use Stillat\StatamicTemplateResolver\StringTemplateManager;
use Wilderborn\Partyline\Facade as Partyline;

class SearchProvider extends Entries
{
    private DocumentSplitter $splitter;

    private StringTemplateManager $templateManager;

    public function __construct(
        DocumentSplitter $splitter
    ) {
        $this->splitter = $splitter;
        $this->templateManager = new StringTemplateManager(config('documentation_search.indexing.template_path', resource_path('views/documentation-search')));
    }

    public static function handle(): string
    {
        return 'docs';
    }

    public static function referencePrefix(): string
    {
        return 'doc';
    }

    public function provide(): Collection
    {
        $config = $this->index->config();

        $headerThreshold = $config['header_threshold'] ?? 1;
        $searchHeadings = $config['search_headings'] ?? ['h2', 'h3', 'h4', 'h5', 'h6'];
        $skipHeaders = $config['skip_headers'] ?? [];
        $skipHeaders = array_map('mb_strtolower', $skipHeaders);

        $configuration = new SplitterConfiguration();
        // +1 to account for the heading we will add to the root of the document.
        $configuration->headerThreshold = $headerThreshold + 1;
        $configuration->searchHeadings = $searchHeadings;
        $configuration->skipHeaders = $skipHeaders;

        $this->splitter->setSplitterConfiguration($configuration);

        $totalSectionsCreated = 0;
        $results = [];

        $entries = parent::provide();

        foreach ($entries as $entry) {
            $blueprint = $entry->blueprint()->handle();
            $collection = $entry->collection()->handle();
            $id = $entry->id();

            if (! $this->templateManager->hasTemplate($collection, $blueprint)) {
                continue;
            }

            $rootData = array_merge(
                $entry->data()->all(), [
                    'id' => $id,
                    'collection' => $collection,
                ]
            );

            $templateData = $entry->toAugmentedArray();
            $templateData['is_rendering_search'] = true;

            $result = $this->templateManager->render($collection, $blueprint, $templateData, function ($template) {
                $template = '<h2>Root Heading</h2>'.$template;

                return Splitter::wrap($template);
            });

            if (! $result) {
                continue;
            }

            $stringValue = new StringValue($result);

            $entryUrl = $entry->url();

            if (! $entryUrl) {
                continue;
            }

            IndexingContent::dispatch($stringValue, $entry);

            $this->splitter->setEntryData($rootData);
            $this->splitter->setEntryUrl($entryUrl);

            $sections = $this->splitter->split($stringValue->value);

            $part = 1;

            $rootData['search_title'] = $rootData['title'] ?? '';
            $rootData['origin_title'] = $rootData['search_title'];
            $rootData['collection'] = $collection;
            $rootData['blueprint'] = $blueprint;
            $rootData['origin_url'] = $entryUrl;
            $rootData['search_url'] = $entryUrl;
            $rootData['is_root'] = true;

            $createdSearchEntries = [];

            foreach ($sections as $section) {
                if ($part > 1) {
                    $rootData['is_root'] = false;
                    $rootData['search_title'] = $section->headerDetails->text;
                    $rootData['search_url'] = $entryUrl.'#'.$section->headerDetails->id;
                }

                $rootData['code_samples'] = $section->codeSamples ?? [];

                $searchContent = new StringValue($section->content);

                CreatingSearchEntry::dispatch(
                    $searchContent,
                    $entry,
                    $rootData,
                    $part
                );

                if (array_key_exists('document_transformers', $config) && is_array($config['document_transformers'])) {
                    $transformers = $config['document_transformers'];

                    foreach ($transformers as $transformer) {
                        $instance = app($transformer);

                        if ($instance instanceof DocumentTransformer) {
                            $instance->handle($section, $entry);
                        }
                    }
                }

                $rootData['search_content'] = $searchContent->value;
                $rootData['additional_context'] = implode(' ', $section->additionalContextData);

                $searchEntry = new SearchEntry();
                $searchEntry->part = $part;
                $searchEntry->data($rootData);

                $results[] = $searchEntry;
                $createdSearchEntries[] = new CreatedSearchEntry($searchEntry, $section);

                $totalSectionsCreated++;
                $part++;
            }

            SearchEntriesCreated::dispatch($createdSearchEntries, $entry);
        }

        Partyline::info('Created '.$totalSectionsCreated.' search sections.');

        return collect($results);
    }

    public function find(array $ids): Collection
    {
        return collect();
    }
}
