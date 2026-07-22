<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\formieratingfield\widgets;

use Craft;

/**
 * Shared site filter behavior for Formie Rating Field dashboard widgets.
 *
 * @since 3.21.0
 */
trait SiteFilterTrait
{
    /**
     * @var string Selected site ID, or "all" for all editable sites
     */
    public string $siteId = 'all';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function siteOptions(): array
    {
        $options = [
            ['value' => 'all', 'label' => Craft::t('formie-rating-field', 'All Sites')],
        ];

        foreach (Craft::$app->getSites()->getEditableSites() as $site) {
            $options[] = [
                'value' => (string) $site->id,
                'label' => $site->name,
            ];
        }

        return $options;
    }

    /**
     * @return int|array<int>
     */
    protected function effectiveSiteId(): int|array
    {
        $editableSiteIds = array_map('intval', $this->editableSiteIds());

        if ($this->siteId === 'all') {
            return $editableSiteIds;
        }

        if ($this->siteId === '' || !ctype_digit($this->siteId)) {
            return [];
        }

        $siteId = (int)$this->siteId;

        return in_array($siteId, $editableSiteIds, true) ? $siteId : [];
    }

    /**
     * @return array<int>
     */
    protected function editableSiteIds(): array
    {
        return Craft::$app->getSites()->getEditableSiteIds();
    }
}
