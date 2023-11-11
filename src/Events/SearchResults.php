<?php

namespace Stillat\DocumentationSearch\Events;

class SearchResults
{
    public function __construct(
        public readonly string $index,
        public readonly string $searchTerm,
        public readonly int $resultCount,
        public readonly int $page,
    ) {
    }
}
