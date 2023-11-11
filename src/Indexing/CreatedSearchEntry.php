<?php

namespace Stillat\DocumentationSearch\Indexing;

use Stillat\DocumentationSearch\Document\DocumentFragment;
use Stillat\DocumentationSearch\SearchEntry;

class CreatedSearchEntry
{
    public function __construct(
        public SearchEntry $searchEntry,
        public DocumentFragment $fragment
    ) {
    }
}
