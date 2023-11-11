<?php

namespace Stillat\DocumentationSearch\Indexing;

use DOMElement;
use DOMNode;
use Stillat\DocumentationSearch\Contracts\HeadingDetailsExtractor as HeadingDetailsExtractorContract;
use Stillat\DocumentationSearch\Support\StringUtilities;

class HeadingDetailsExtractor implements HeadingDetailsExtractorContract
{
    protected string $headingClass = '';

    public function __construct(string $headingClass)
    {
        $this->headingClass = $headingClass;
    }

    private function hasClass(DOMElement $node, string $class): bool
    {
        if ($node->hasAttribute('class')) {
            $classes = explode(
                ' ',
                $node->getAttribute('class')
            );

            if (in_array($class, $classes)) {
                return true;
            }
        }

        return false;
    }

    private function containsPermalink(DOMNode $node): bool
    {
        if ($node instanceof DOMElement &&
            $this->hasClass($node, $this->headingClass)) {
            return true;
        }

        return false;
    }

    public function extract(ExtractionValues $values): HeaderDetails
    {
        $id = $values->heading->getAttribute('id');
        $text = StringUtilities::cleanStringHash(
            $values->heading->nodeValue
        );

        // The heading has no ID, so we need to try and find a link.
        if (! $id) {
            /** @var DOMNode[] $links */
            $links = iterator_to_array(
                $values->heading->getElementsByTagname('a')
            );

            if (count($links) > 0) {
                foreach ($links as $link) {
                    if ($this->containsPermalink($link)) {
                        $id = StringUtilities::cleanStringHash(
                            $link->getAttribute('href')
                        );
                    }
                }
            }
        }

        return new HeaderDetails($id, $text);
    }
}
