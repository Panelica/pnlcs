<?php

namespace AapanelQuality\Sniffs\Metrics;

use PHP_CodeSniffer\Standards\Generic\Sniffs\Metrics\CyclomaticComplexitySniff;

/** Dispatch the unchanged upstream metric to anonymous and arrow functions. */
final class ClosureComplexitySniff extends CyclomaticComplexitySniff
{
    public function register()
    {
        return [T_CLOSURE, T_FN];
    }
}
