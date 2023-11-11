<?php

namespace Stillat\DocumentationSearch\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Stillat\DocumentationSearch\Indexing\CreatedSearchEntry;

class SearchEntriesCreated
{
    use Dispatchable;

    public function __construct(
        /** @var CreatedSearchEntry[] $sections */
        public array $sections,
        public $entry
    ) {
    }
}
