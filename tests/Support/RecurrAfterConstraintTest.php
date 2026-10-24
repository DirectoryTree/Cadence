<?php

use DirectoryTree\Cadence\Support\RecurrAfterConstraint;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;

it('stops transforming after finding the first usable occurrence', function ($expression, $expected, $evaluated) {
    $rule = new Rule($expression);
    $constraint = new class(new DateTime('2026-05-01 10:00:00'), $rule->getCount(), $rule->getExDates()) extends RecurrAfterConstraint
    {
        public int $evaluated = 0;

        public function test(DateTimeInterface $date): bool
        {
            $this->evaluated++;

            return parent::test($date);
        }
    };

    $occurrences = (new ArrayTransformer)->transform($rule, $constraint, false);

    expect($occurrences)->toHaveCount(1)
        ->and($occurrences->first()->getStart()->format('Y-m-d H:i:s'))->toBe($expected)
        ->and($constraint->evaluated)->toBe($evaluated);
})->with([
    'next occurrence' => ['FREQ=DAILY;DTSTART=20260501T090000', '2026-05-02 09:00:00', 3],
    'excluded occurrence' => ['FREQ=DAILY;EXDATE=20260502T090000;DTSTART=20260501T090000', '2026-05-03 09:00:00', 4],
]);
