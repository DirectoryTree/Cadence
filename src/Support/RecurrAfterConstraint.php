<?php

namespace DirectoryTree\Cadence\Support;

use DateTimeInterface;
use Recurr\Transformer\Constraint\AfterConstraint;

class RecurrAfterConstraint extends AfterConstraint
{
    /**
     * The number of occurrences evaluated since the rule's start.
     */
    protected int $occurrences = 0;

    /**
     * The maximum number of occurrences in the rule.
     */
    protected ?int $count;

    /**
     * Create a constraint that preserves COUNT while filtering past occurrences.
     */
    public function __construct(DateTimeInterface $after, ?int $count = null)
    {
        parent::__construct($after);

        $this->count = $count;
    }

    /**
     * Determine if the occurrence is after the cutoff and within the rule's count.
     */
    public function test(DateTimeInterface $date): bool
    {
        if ($this->count !== null && ++$this->occurrences > $this->count) {
            $this->stopsTransformer = true;

            return false;
        }

        return parent::test($date);
    }
}
