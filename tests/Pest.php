<?php

use Stillat\DocumentationSearch\Contracts\DocumentSplitter;

uses(Tests\TestCase::class)->in('Feature');

function split(string $html): array
{
    /** @var DocumentSplitter $splitter */
    $splitter = app(DocumentSplitter::class);

    return $splitter->split($html);
}
