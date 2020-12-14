<?php

namespace Marquee\Core\String;

use Generator;

class Regex
{
    const IGNORE_CASE = 1;
    const MATCH_ALL   = 2;
    const MULTILINE   = 4;
    protected string $pattern;
    protected int    $flags;

    public function __construct(string $pattern, int $flags)
    {
        $this->pattern = $pattern;
        $this->flags   = $flags;
    }

    public function match(string $input, array &$output = []): int
    {
        return preg_match($this->buildPattern(), $input, $output);
    }

    public function matches(string $input, int &$count = 0, int &$valid = 0): Generator
    {
        $valid = preg_match($this->buildPattern(), $input, $output);
        $count = count($output) - 1;

        foreach ($output as $i => $match) {
            if ($i > 0) {
                yield $match;
            }
        }
    }

    private function buildPattern(): string
    {
        static $flags = [
            'i', 's', 'm',
        ];

        /**
         * Build Flag String
         */
        $pattern = sprintf('/%s/', $this->pattern);
        foreach ($flags as $i => $flag) {
            if (((1 << $i) & $this->flags) === (1 << $i)) {
                $pattern .= $flag;
            }
        }

        return $pattern;
    }
}