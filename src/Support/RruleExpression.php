<?php

namespace DirectoryTree\Cadence\Support;

class RruleExpression
{
    /**
     * Extract the DTSTART value from an RRULE expression.
     *
     * @return array{string, string|null}
     */
    public static function extractDtstart(string $expression): array
    {
        if (preg_match('/DTSTART=([^;]+)/i', $expression, $matches)) {
            $expression = preg_replace('/;?DTSTART=[^;]+;?/i', ';', $expression);

            return [trim($expression, ';'), $matches[1]];
        }

        return [$expression, null];
    }
}
