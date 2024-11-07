<?php

namespace Stillat\DocumentationSearch\Contracts;

use Statamic\Contracts\Entries\Entry;

interface ContentRetriever
{
    public function getContent(Entry $entry): string;
}
