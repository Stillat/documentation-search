<?php

namespace Stillat\DocumentationSearch\Document;

class SplitterConfiguration
{
    public int $headerThreshold = 1;

    public array $searchHeadings = ['h2', 'h3', 'h4', 'h5', 'h6'];

    public array $skipHeaders = [];
}
