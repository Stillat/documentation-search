<?php

namespace Stillat\DocumentationSearch\Tags;

use Statamic\Extensions\Pagination\LengthAwarePaginator;
use Statamic\Facades\Entry;
use Statamic\Search\Tags;
use Statamic\Tags\Concerns\OutputsItems;
use Statamic\View\Antlers\AntlersString;
use Stillat\DocumentationSearch\Contracts\QueryTransformer;
use Stillat\DocumentationSearch\Events\SearchComplete;
use Stillat\DocumentationSearch\Events\SearchResults;

class Documentation extends Tags
{
    use OutputsItems;

    protected static $handle = 'documentation';

    private function getSearchTerms()
    {
        return $this->params->get('for') ?? request($this->params->get('query', 'q'));
    }

    protected function applyQueryTransformers($query)
    {
        $index = $this->params->get('index', 'default');
        $indexConfig = config('statamic.search.indexes.'.$index, []);

        if (! array_key_exists('query_transformers', $indexConfig) || empty($indexConfig['query_transformers'])) {
            return;
        }

        $transformers = $indexConfig['query_transformers'];

        foreach ($transformers as $transformer) {
            $instance = app($transformer);

            if ($instance instanceof QueryTransformer) {
                $query = $instance->handle($query);
            }
        }

        $this->params['for'] = $query;
    }

    public function results()
    {
        if (! $query = $this->getSearchTerms()) {
            return $this->parseNoResults();
        }

        $originalSearchTerm = $query;

        $this->applyQueryTransformers($query);

        $pageName = $this->params->get('page_name', 'page');
        $originalLimit = $this->params->get('limit', null);

        if ($originalLimit == null || ! is_numeric($originalLimit)) {
            $originalLimit = $this->params->get('paginate', null);
        }

        if ($originalLimit == null || ! is_numeric($originalLimit)) {
            $originalLimit = 10;
        }

        $alias = $this->params->get('alias', 'results');
        $supplement = $this->params->get('supplement_data', true);

        // Set this to false for the underlying Statamic tag.
        $this->params['supplement_data'] = false;

        // Remove pagination-related parameters from
        // the query so we can get all results. We
        // will paginate the results ourselves.
        unset($this->params['paginate']);
        unset($this->params['limit']);

        $results = parent::results();

        $groupResults = $this->params->get('group_results', false);

        if ($results instanceof AntlersString) {
            $results = collect();
        } else {
            $results = $results['results'];

            if ($groupResults) {
                $results = $results->groupBy('id');
            }
        }

        $allResultCount = $results->count();

        SearchComplete::dispatch(new SearchResults(
            $this->params->get('index', 'default'),
            $originalSearchTerm,
            $allResultCount,
            intval(request()->get($pageName, 1))
        ));

        $queryResults = [];

        foreach ($results as $groupedItem) {
            if (! $groupResults) {
                $itemArray = [$groupedItem];
            } else {
                $itemArray = $groupedItem->toArray();
            }

            $queryResults[] = [
                'items' => $itemArray,
            ];
        }

        $queryResults = collect($queryResults)
            ->forPage(request()->get($pageName, 1), $originalLimit)
            ->toArray();

        if ($supplement) {
            $ids = [];

            foreach ($queryResults as $item) {
                $ids[] = $item['items'][0]->get('id');
            }

            $entries = Entry::query()
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id')
                ->map(fn ($entry) => $entry->toAugmentedArray());

            foreach ($queryResults as $item) {
                foreach ($item['items'] as $result) {
                    $id = $result->get('id');

                    foreach ($entries[$id] as $key => $entryValue) {
                        $result->setSupplement($key, $entryValue);
                    }
                }
            }
        }

        $paginator = new LengthAwarePaginator(
            $queryResults,
            $allResultCount,
            $originalLimit,
            request()->get($pageName, 1),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );

        $paginator->appends(request()->all());

        return [
            $alias => $queryResults,
            'no_results' => $allResultCount == 0,
            'total_results' => count($queryResults),
            'paginate' => $this->getPaginationData($paginator),
        ];
    }
}
