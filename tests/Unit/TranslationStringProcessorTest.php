<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Services\TranslationStringProcessor;

beforeEach(function () {
    $this->processor = new TranslationStringProcessor;
});

it('detects strings wrapped in double quotes', function () {
    expect($this->processor->isWrappedWithDoubleQuotes('"Hello"'))->toBeTrue()
        ->and($this->processor->isWrappedWithDoubleQuotes('"Hello World"'))->toBeTrue()
        ->and($this->processor->isWrappedWithDoubleQuotes('Hello'))->toBeFalse()
        ->and($this->processor->isWrappedWithDoubleQuotes("'Hello'"))->toBeFalse()
        ->and($this->processor->isWrappedWithDoubleQuotes(' "Hello" '))->toBeTrue(); // Trims whitespace
});

it('unwraps outer double quotes', function () {
    expect($this->processor->unwrapOuterDoubleQuotes('"Hello"'))->toBe('Hello')
        ->and($this->processor->unwrapOuterDoubleQuotes(' "World" '))->toBe('World')
        ->and($this->processor->unwrapOuterDoubleQuotes('No Quotes'))->toBe('No Quotes');
});

it('masks placeholders correctly', function () {
    $text = 'Welcome, :name! You have :count items.';
    [$masked, $map] = $this->processor->maskPlaceholders($text);

    expect($masked)->toBe('Welcome, [[[PLH1]]]! You have [[[PLH2]]] items.')
        ->and($map)->toBe([
            '[[[PLH1]]]' => ':name',
            '[[[PLH2]]]' => ':count',
        ]);
});

it('unmasks placeholders correctly', function () {
    $masked = 'Welcome, [[[PLH1]]]! You have [[[PLH2]]] items.';
    $map = [
        '[[[PLH1]]]' => ':name',
        '[[[PLH2]]]' => ':count',
    ];

    $unmasked = $this->processor->unmaskPlaceholders($masked, $map);

    expect($unmasked)->toBe('Welcome, :name! You have :count items.');
});

it('validates placeholders matching', function () {
    $source = 'Hello :name';

    // Correct
    expect($this->processor->validatePlaceholders($source, 'Hola :name'))->toBeTrue();

    // Missing
    expect($this->processor->validatePlaceholders($source, 'Hola'))->toBeFalse();

    // Extra
    expect($this->processor->validatePlaceholders($source, 'Hola :name :other'))->toBeFalse();

    // Mismatch name
    expect($this->processor->validatePlaceholders($source, 'Hola :nombre'))->toBeFalse();
});
