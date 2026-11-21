<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * How much an attention point of a page weighs: the colour of its dot, and its place in the list.
 */
enum AttentionSeverity: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * The place in the list: the urgent first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
        };
    }
}
