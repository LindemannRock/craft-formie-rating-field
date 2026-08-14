<?php
/**
 * Test-compatible CascadeCache stand-in for environments without the optional package.
 *
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace craft\cachecascade;

use yii\caching\ArrayCache;

if (!class_exists(CascadeCache::class)) {
    /**
     * Craft Cloud-style cache-cascade stand-in.
     *
     * @since 3.22.0
     */
    class CascadeCache extends ArrayCache
    {
        public function hiddenPrimary(): never
        {
            throw new \LogicException('The hidden primary must not be inspected.');
        }
    }
}
