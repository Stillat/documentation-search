<?php

namespace Stillat\DocumentationSearch\Indexing\Transformers\Query;

use Stillat\DocumentationSearch\Contracts\QueryTransformer;
use Stillat\DocumentationSearch\Support\StringUtilities;

class SoundexTransformer implements QueryTransformer
{
    public function handle(string $value): string
    {
        return $value.' '.StringUtilities::soundexWords($value);
    }
}
