<?php

namespace Stillat\DocumentationSearch\Document;

use DOMNode;

class ElementSplitter
{
    protected string $nodeValue = '';

    protected Splitter $splitter;

    protected array $sections = [];

    public function __construct(protected Splitter $parentSplitter, protected DOMNode $node)
    {
        $this->nodeValue = $node->nodeValue;
        $this->splitter = new Splitter($this->parentSplitter->getHeadingDetailsExtractor());
        $this->splitter->setParent($this->parentSplitter->getRoot());

        $this->sections = $this->splitter->split(Splitter::wrap($this->parentSplitter->cloneNodeContent($this->node)));
    }

    public function getContentWithAttribute($attributeName): string
    {
        if (! $this->hasSections()) {
            return $this->nodeValue;
        }

        $content = $this->sections[0]->content ?? '';
        $attributeValue = $this->getAttribute($attributeName) ?? '';

        if (mb_strlen(trim($attributeValue)) > 0 && $attributeValue != $content) {
            $content .= ' ('.$attributeValue.')';
        }

        return $content;
    }

    public function getContent(): string
    {
        if (! $this->hasSections()) {
            return $this->nodeValue;
        }

        return $this->sections[0]->content ?? '';
    }

    public function hasSections(): bool
    {
        return count($this->sections) > 0;
    }

    public function getAttribute(string $name): ?string
    {
        return $this->splitter->getAttributeFor('//div', $name);
    }
}
