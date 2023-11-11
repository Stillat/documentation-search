<?php

namespace Stillat\DocumentationSearch\Indexing;

use DOMNode;

class ExtractionValues
{
    public function __construct(
        public DOMNode $heading,
        public string $content,
        public string $entryUrl,
        public $entry
    ) {
    }
}
