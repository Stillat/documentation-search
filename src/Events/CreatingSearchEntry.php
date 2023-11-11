<?php

namespace Stillat\DocumentationSearch\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CreatingSearchEntry
{
    use Dispatchable;

    public function __construct(
        public StringValue $searchContent,
        public $entry,
        public array $data,
        public int $partNumber,
    ) {
    }
}
