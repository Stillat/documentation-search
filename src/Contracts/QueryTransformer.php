<?php

namespace Stillat\DocumentationSearch\Contracts;

interface QueryTransformer
{
    public function handle(string $value): string;
}
