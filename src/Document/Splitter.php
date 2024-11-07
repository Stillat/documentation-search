<?php

namespace Stillat\DocumentationSearch\Document;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use DOMText;
use DOMXPath;
use Exception;
use Illuminate\Support\Str;
use Stillat\DocumentationSearch\Contracts\DocumentSplitter;
use Stillat\DocumentationSearch\Contracts\HeadingDetailsExtractor;
use Stillat\DocumentationSearch\Indexing\ExtractionValues;

class Splitter implements DocumentSplitter
{
    private HeadingDetailsExtractor $headingDetailsExtractor;

    protected string $entryUrl = '';

    protected $entryData = null;

    protected SplitterConfiguration $configuration;

    public function __construct(HeadingDetailsExtractor $headingDetailsExtractor)
    {
        $this->headingDetailsExtractor = $headingDetailsExtractor;
        $this->configuration = new SplitterConfiguration();
    }

    protected bool $breakOnHeading = true;

    protected bool $isRoot = true;

    protected array $elementsWithContextualTitle = [
        'abbr', 'acronym', 'a',
    ];

    protected array $ignoreElements = [
        'area', 'map', 'input', 'script', 'style', 'noscript',
        'meta', 'link', 'head', 'title', 'iframe', 'svg',
        'canvas', 'audio', 'video', 'source', 'track', 'embed',
        'dialog', 'image', 'object', 'param', 'portal',
        'search', 'select', 'textarea', 'xmp',
    ];

    protected array $documentHeadings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    protected ?DOMDocument $dom = null;

    protected array $sections = [];

    protected array $codeSamples = [];

    protected string $buffer = '';

    protected ?DOMNode $activeHeading = null;

    protected bool $inList = false;

    protected array $activeListContents = [];

    protected ?Splitter $parent = null;

    protected array $additionalContext = [];

    public function setSplitterConfiguration(SplitterConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getHeadingDetailsExtractor(): HeadingDetailsExtractor
    {
        return $this->headingDetailsExtractor;
    }

    public function setParent(Splitter $parent): void
    {
        $this->isRoot = false;

        $this->parent = $parent;
    }

    public function getParent(): ?Splitter
    {
        return $this->parent;
    }

    public function setIsRoot(bool $isRoot): Splitter
    {
        $this->isRoot = $isRoot;

        return $this;
    }

    public function getRoot(): Splitter
    {
        if ($this->parent === null) {
            return $this;
        }

        return $this->parent->getRoot();
    }

    public function pushAdditionalContext(string $context): void
    {
        $root = $this->getRoot();

        $root->addAdditionalContext($context);
    }

    public function addAdditionalContext(string $context): void
    {
        $this->additionalContext[] = $context;
    }

    public function pushCodeSample(string $sample, string $language): void
    {
        $root = $this->getRoot();

        $root->addCodeSample($sample, $language);
    }

    public function addCodeSample(string $sample, string $language): void
    {
        $this->codeSamples[] = [
            'lang' => $language,
            'sample' => $sample,
        ];
    }

    public function setIsList(bool $isList): Splitter
    {
        $this->inList = $isList;

        return $this;
    }

    public function setBreakOnHeadings(bool $breakOnHeadings): Splitter
    {
        $this->breakOnHeading = $breakOnHeadings;

        return $this;
    }

    public static function wrap($content): string
    {
        return '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><body>'.$content.'</body></html>';
    }

    public function getDom(): DOMDocument
    {
        return $this->dom;
    }

    /**
     * @throws DOMException
     */
    public function cloneNodeContent(DOMNode $node): string
    {
        if (in_array($node->nodeName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            $newWrapper = $this->dom->createElement('p', '');
        } else {
            $newWrapper = $this->dom->createElement('div', '');
        }

        foreach ($node->attributes as $attribute) {
            if ($attribute->nodeName == 'data-indexer') {
                continue;
            }

            $newWrapper->setAttribute($attribute->nodeName, $attribute->nodeValue);
        }

        foreach ($node->childNodes as $childNode) {
            $newWrapper->appendChild($childNode->cloneNode(true));
        }

        return $this->dom->saveHTML($newWrapper);
    }

    protected function extractNodeContentWithAttribute(DOMNode $node, $attribute): string
    {
        return (new ElementSplitter($this, $node))->getContentWithAttribute($attribute);
    }

    protected function extractCleanNodeContent(DOMNode $node): string
    {
        return (new ElementSplitter($this, $node))->getContent();
    }

    protected function makeSplitter(): Splitter
    {
        $splitter = new Splitter($this->headingDetailsExtractor);
        $splitter->setParent($this->getRoot());

        return $splitter;
    }

    /**
     * @throws DOMException
     */
    protected function extractAsideContent(DOMNode $node): string
    {
        $splitter = $this->makeSplitter();
        $splitter->setIsList(false)
            ->setBreakOnHeadings(false)
            ->split(self::wrap($this->cloneNodeContent($node)));

        return $splitter->getContent();
    }

    /**
     * @throws DOMException
     */
    protected function extractCleanListContent(DOMNode $node): string
    {
        $splitter = $this->makeSplitter();
        $splitter->setIsList(true)
            ->split(self::wrap($this->cloneNodeContent($node)));

        return $splitter->getContent();
    }

    public function getContent()
    {
        if ($this->inList) {
            return implode(', ', $this->activeListContents);
        }

        if (count($this->sections) == 0) {
            return '';
        }

        return $this->sections[0]['content'] ?? '';
    }

    protected function append($content): void
    {
        $content = trim($content);

        if (mb_strlen($content) == 0) {
            return;
        }

        if ($this->inList) {
            $this->activeListContents[] = $content;

            return;
        }

        if (mb_strlen($this->buffer) > 0) {
            $this->buffer .= ' ';
        }

        $this->buffer .= $content;
    }

    protected function appendListContent($content): void
    {
        $content = trim($content);

        if (mb_strlen($content) == 0) {
            return;
        }

        if (mb_strlen($this->buffer) > 0) {
            $lastChar = mb_substr($this->buffer, -1);

            if (ctype_punct($lastChar)) {
                $this->buffer .= ' ';
            } else {
                $this->buffer .= ': ';
            }
        }

        $this->buffer .= $content;
    }

    protected function shouldIgnoreNode(DOMNode $node): bool
    {
        if ($node->hasAttribute('data-indexer') && $node->getAttribute('data-indexer') === 'ignore') {
            return true;
        }

        return false;
    }

    protected function shouldIsolateNode(DOMNode $node): bool
    {
        if (! method_exists($node, 'hasAttribute') || ! method_exists($node, 'getAttribute')) {
            return false;
        }

        if ($node->hasAttribute('data-indexer') && $node->getAttribute('data-indexer') === 'nobreak') {
            return true;
        }

        return false;
    }

    /**
     * @throws DOMException
     */
    protected function walkDom(DOMNode $node): void
    {
        if (in_array($node->nodeName, $this->ignoreElements)) {
            return;
        }

        if ($this->breakOnHeading && Str::startsWith($node->nodeName, $this->documentHeadings) && ! $this->shouldIgnoreNode($node)) {
            if (mb_strlen(trim($this->buffer)) > 0) {
                $this->sections[] = [
                    'heading' => $this->activeHeading,
                    'content' => $this->buffer,
                    'code_samples' => $this->codeSamples,
                    'additional_content' => $this->additionalContext,
                ];
            }

            $this->additionalContext = [];
            $this->codeSamples = [];
            $this->buffer = '';
            $this->activeHeading = $node;
            $this->inList = false;
        } else {
            if ($node->nodeType === XML_TEXT_NODE) {
                $this->append($node->nodeValue);
            }
        }

        if ($node->nodeName == 'p' && $node->hasAttributes() && $node->attributes->getNamedItem('class') !== null) {
            $class = $node->attributes->getNamedItem('class')->nodeValue;

            if ($class == '__search_only_context') {
                $this->pushAdditionalContext($node->nodeValue);

                return;
            }
        }

        if (in_array($node->nodeName, $this->elementsWithContextualTitle)) {
            if ($node->hasAttributes() && $node->attributes->getNamedItem('title') !== null) {
                $this->append($this->extractNodeContentWithAttribute($node, 'title'));

                return;
            }
        }

        if (! $node instanceof DOMText && $this->shouldIsolateNode($node)) {
            $this->append($this->extractAsideContent($node));

            return;
        }

        if ($node->nodeName == 'pre' || $node->nodeName == 'code' || $node->nodeName == 'samp') {
            $codeResult = $this->extractCleanNodeContent($node);

            if (Str::contains($codeResult, "\n")) {
                $nodeToCheck = $node;

                if (! $node->hasAttributes() && $node->parentNode !== null) {
                    if ($node->parentNode->hasAttributes()) {
                        $nodeToCheck = $node->parentNode;
                    }
                }

                $this->pushCodeSample($codeResult, $this->extractLanguageFromNode($nodeToCheck));
            } else {
                $this->append($codeResult);
            }

            return;
        }

        if ($node->nodeName == 'ul' || $node->nodeName == 'ol' || $node->nodeName == 'menu') {
            $this->appendListContent($this->extractCleanListContent($node));

            return;
        }

        if ($node->nodeName == 'aside') {
            $this->append($this->extractAsideContent($node));

            return;
        }

        if (! Str::startsWith($node->nodeName, $this->documentHeadings) || ! $this->breakOnHeading) {
            foreach ($node->childNodes as $childNode) {
                $this->walkDom($childNode);
            }
        }
    }

    public function getAttributeFor($xPathQuery, $attributeName)
    {
        $xPath = new DOMXPath($this->dom);
        $node = $xPath->query($xPathQuery)->item(0);

        if (! $node) {
            return null;
        }

        if ($node instanceof DOMElement) {
            if ($node->hasAttribute($attributeName)) {
                return $node->getAttribute($attributeName);
            }
        }

        return null;
    }

    protected function extractLanguageFromNode(DOMNode $node): string
    {
        if ($node->hasAttributes()) {
            $langAttribute = $node->attributes->getNamedItem('lang');
            if ($langAttribute) {
                return $langAttribute->nodeValue;
            }

            $classAttribute = $node->attributes->getNamedItem('class');
            if ($classAttribute) {
                $classes = explode(' ', $classAttribute->nodeValue);
                foreach ($classes as $class) {
                    if (preg_match('/^language-(\w+)$/', $class, $matches) || preg_match('/^lang-(\w+)$/', $class, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }

        return '';
    }

    /**
     * @throws DOMException
     */
    protected function processSections(): void
    {
        $this->walkDom($this->dom->documentElement);

        if (mb_strlen(trim($this->buffer)) > 0) {
            $this->sections[] = [
                'heading' => $this->activeHeading,
                'content' => $this->buffer,
                'code_samples' => $this->codeSamples,
                'additional_content' => $this->additionalContext,
            ];
        }

        $this->additionalContext = [];
        $this->codeSamples = [];
        $this->buffer = '';
    }

    public function setEntryUrl($url)
    {
        $this->entryUrl = $url;
    }

    public function setEntryData($data)
    {
        $this->entryData = $data;
    }

    protected function getNodesByTagNames(array $tagNames): array
    {
        $nodes = [];

        foreach ($tagNames as $tagName) {
            $nodes = array_merge(
                $nodes,
                iterator_to_array($this->dom->getElementsByTagName($tagName))
            );
        }

        return $nodes;
    }

    /**
     * @return DocumentFragment[]
     *
     * @throws DOMException
     */
    public function split($content): array
    {
        $this->sections = [];
        $this->activeHeading = null;

        $currentErrorLevel = libxml_use_internal_errors();
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);

        try {
            $this->dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } catch (Exception $e) {
            libxml_use_internal_errors($currentErrorLevel);

            return [];
        }

        libxml_use_internal_errors($currentErrorLevel);

        $this->processSections();

        $documentFragments = [];

        foreach ($this->sections as $section) {
            $fragment = new DocumentFragment();
            $fragment->content = $section['content'];
            $fragment->codeSamples = $section['code_samples'];
            $fragment->additionalContextData = $section['additional_content'];

            if ($this->parent == null && $section['heading'] != null) {
                $fragment->headerDetails = $this->headingDetailsExtractor->extract(new ExtractionValues(
                    $section['heading'],
                    $section['content'],
                    $this->entryUrl,
                    $this->entryData
                ));
            }

            if ($fragment->headerDetails != null) {
                if (in_array(mb_strtolower($fragment->headerDetails->text), $this->configuration->skipHeaders)) {
                    continue;
                }
            }

            $documentFragments[] = $fragment;
        }

        if ($this->isRoot) {
            $headings = $this->getNodesByTagNames($this->configuration->searchHeadings);

            if (count($headings) < $this->configuration->headerThreshold) {
                if (count($this->sections) == 0) {
                    return [];
                }

                // Convert all fragments to a single fragment.
                $documentContent = '';
                $codeSamples = [];
                $additionalContext = [];

                foreach ($documentFragments as $fragment) {
                    $documentContent .= $fragment->content;
                    $codeSamples += $fragment->codeSamples;
                    $additionalContext += $fragment->additionalContextData;
                }

                $documentFragment = new DocumentFragment();
                $documentFragment->content = $documentContent;
                $documentFragment->codeSamples = $codeSamples;
                $documentFragment->additionalContextData = $additionalContext;

                $documentFragments = [$documentFragment];
            }
        }

        return $documentFragments;
    }
}
