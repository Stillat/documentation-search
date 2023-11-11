<?php

namespace Stillat\DocumentationSearch;

use Statamic\Contracts\Search\Searchable as SearchableContract;
use Statamic\Data\ContainsData;
use Statamic\Search\Searchable;

class SearchEntry implements SearchableContract
{
    use ContainsData, Searchable;

    public int $part = 0;

    public function id()
    {
        return $this->get('id');
    }

    public function getSearchReference(): string
    {
        return "doc::{$this->part}:{$this->id()}";
    }
}
