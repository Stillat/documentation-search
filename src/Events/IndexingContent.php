<?php

namespace Stillat\DocumentationSearch\Events;

use Illuminate\Foundation\Events\Dispatchable;

class IndexingContent
{
    use Dispatchable;

    public function __construct(
        public StringValue $value,
        public $entry,
    ) {
    }
}
