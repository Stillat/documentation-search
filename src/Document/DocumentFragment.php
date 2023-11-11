<?php

namespace Stillat\DocumentationSearch\Document;

use Stillat\DocumentationSearch\Indexing\HeaderDetails;

class DocumentFragment
{
    public ?HeaderDetails $headerDetails = null;

    public string $content = '';

    public array $codeSamples = [];

    public array $additionalContextData = [];
}
