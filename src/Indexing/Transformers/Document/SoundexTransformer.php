<?php

namespace Stillat\DocumentationSearch\Indexing\Transformers\Document;

use Stillat\DocumentationSearch\Contracts\DocumentTransformer;
use Stillat\DocumentationSearch\Document\DocumentFragment;
use Stillat\DocumentationSearch\Support\StringUtilities;

class SoundexTransformer implements DocumentTransformer
{
    public function handle(DocumentFragment $fragment, $entry): void
    {
        $fragment->additionalContextData[] = StringUtilities::soundexWords($fragment->content);
    }
}
