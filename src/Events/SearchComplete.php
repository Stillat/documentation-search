<?php

namespace Stillat\DocumentationSearch\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SearchComplete
{
    use Dispatchable;

    public function __construct(
        public SearchResults $results,
    ) {
    }
}
