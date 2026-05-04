<?php

use DirectoryTree\Cadence\Support\RruleExpression;

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
