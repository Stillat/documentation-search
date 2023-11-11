<?php

namespace Stillat\DocumentationSearch\Contracts;

use Stillat\DocumentationSearch\Document\DocumentFragment;

interface DocumentTransformer
{
    public function handle(DocumentFragment $fragment, $entry): void;
}
