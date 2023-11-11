<?php

namespace Stillat\DocumentationSearch\Contracts;

use Stillat\DocumentationSearch\Indexing\ExtractionValues;
use Stillat\DocumentationSearch\Indexing\HeaderDetails;

interface HeadingDetailsExtractor
{
    public function extract(ExtractionValues $values): HeaderDetails;
}
