<?php

use DirectoryTree\Cadence\Support\RruleExpression;

it('recognizes explicit starts in inline and RFC expressions', function ($expression) {
    expect(RruleExpression::hasDtstart($expression))->toBeTrue();
})->with([
    'FREQ=DAILY;DTSTART=20260501T090000',
    'dtstart=20260501T090000;FREQ=DAILY',
    "DTSTART:20260501T090000Z\nRRULE:FREQ=DAILY",
    "DTSTART;TZID=America/New_York:20260501T090000\nRRULE:FREQ=DAILY",
]);

it('recognizes expressions without an explicit start', function () {
    expect(RruleExpression::hasDtstart('FREQ=DAILY;COUNT=5'))->toBeFalse();
});

it('extracts dtstart from expression', function () {
    [$expression, $dtstart] = RruleExpression::extractDtstart('FREQ=DAILY;DTSTART=20260501T090000');

    expect($expression)->toBe('FREQ=DAILY')
        ->and($dtstart)->toBe('20260501T090000');
});

it('extracts dtstart from the beginning of expression', function () {
    [$expression, $dtstart] = RruleExpression::extractDtstart('DTSTART=20260501T090000;FREQ=DAILY');

    expect($expression)->toBe('FREQ=DAILY')
        ->and($dtstart)->toBe('20260501T090000');
});

it('extracts dtstart from the middle of expression', function () {
    [$expression, $dtstart] = RruleExpression::extractDtstart('FREQ=DAILY;DTSTART=20260501T090000;COUNT=5');

    expect($expression)->toBe('FREQ=DAILY;COUNT=5')
        ->and($dtstart)->toBe('20260501T090000');
});

it('returns null dtstart when not present', function () {
    [$expression, $dtstart] = RruleExpression::extractDtstart('FREQ=DAILY;COUNT=5');

    expect($expression)->toBe('FREQ=DAILY;COUNT=5')
        ->and($dtstart)->toBeNull();
});

it('handles expression with only dtstart', function () {
    [$expression, $dtstart] = RruleExpression::extractDtstart('DTSTART=20260501T090000');

    expect($expression)->toBe('')
        ->and($dtstart)->toBe('20260501T090000');
});
