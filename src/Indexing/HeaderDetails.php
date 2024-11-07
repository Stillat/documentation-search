<?php

namespace Stillat\DocumentationSearch\Indexing;

class HeaderDetails
{
    public function __construct(
        public string $id,
        public string $text,
        public int $level = 1,
    ) {
    }
}
