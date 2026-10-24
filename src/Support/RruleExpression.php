<?php

namespace DirectoryTree\Cadence\Support;

class RruleExpression
{
    /**
     * Determine if the expression includes an explicit start.
     */
    public static function hasDtstart(string $expression): bool
    {
        return (bool) preg_match('/(?:^|[;\r\n])DTSTART[=;:]/i', $expression);
    }

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
