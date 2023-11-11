<?php

namespace Stillat\DocumentationSearch\Events;

class StringValue
{
    public function __construct(
        public string $value
    ) {
    }
}
