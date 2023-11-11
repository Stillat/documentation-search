<?php

test('splitter can split a document without headings', function () {
    $doc = <<<'HTML'
<p>Some content</p>
HTML;

    $results = split($doc);
    expect($results)->toHaveCount(1);
    expect($results[0]->content)->toBe('Some content');
});

test('splitter can split documents with headings', function () {
    $doc = <<<'HTML'
Leading Content
<h1>Heading 1</h1>
<p>Some content 1</p>
<h2>Heading 2</h2>
<p>Some content 2</p>
<h1>Heading 3</h1>
<p>Some content 3</p>
HTML;

    $results = split($doc);
    expect($results)->toHaveCount(4);
    expect($results[0]->content)->toBe('Leading Content');
    expect($results[1]->content)->toBe('Some content 1');
    expect($results[2]->content)->toBe('Some content 2');
    expect($results[3]->content)->toBe('Some content 3');
});

test('splitter can ignore headings', function () {
    $doc = <<<'HTML'
Leading Content
<h1>Heading 1</h1>
<p>Some content 1</p>
<h2 data-indexer="ignore">Heading 2</h2>
<p>Some content 2</p>
HTML;

    $results = split($doc);
    expect($results)->toHaveCount(2);
    expect($results[0]->content)->toBe('Leading Content');
    expect($results[1]->content)->toBe('Some content 1 Some content 2');
});

test('splitter can ignore regions', function () {
    $doc = <<<'HTML'
Leading Content
<h1>Heading 1</h1>
<div data-indexer="nobreak">
<p>Some content 1</p>
<h2>Heading 2</h2>
<p>Some content 2</p>
<h1>Heading 3</h1>
<p>Some content 3</p>
</div>
HTML;

    $results = split($doc);
    expect($results)->toHaveCount(2);
    expect($results[0]->content)->toBe('Leading Content');
    expect($results[1]->content)->toBe('Some content 1 Heading 2 Some content 2 Heading 3 Some content 3');
});
