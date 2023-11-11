<?php

namespace Stillat\DocumentationSearch\Contracts;

use Stillat\DocumentationSearch\Document\DocumentFragment;
use Stillat\DocumentationSearch\Document\SplitterConfiguration;

interface DocumentSplitter
{
    public function setSplitterConfiguration(SplitterConfiguration $configuration);

    public function setEntryUrl($url);

    public function setEntryData($data);

    /**
     * @return DocumentFragment[]
     */
    public function split(string $content): array;
}
