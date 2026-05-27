<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\FieldResolver\CartRule;

use Magebit\McpMarketingTools\Api\CartRuleFieldResolverInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\Data\RuleLabelInterface;

/**
 * Store-view label overrides as a flat `{store_id, store_label}` list.
 */
class StoreLabelsResolver implements CartRuleFieldResolverInterface
{
    public const KEY = 'store_labels';

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 80;
    }

    /**
     * @inheritDoc
     */
    public function resolve(RuleInterface $rule, array $args): array
    {
        unset($args);
        $labels = $rule->getStoreLabels();
        if (!is_array($labels)) {
            return [];
        }
        $rows = [];
        foreach ($labels as $label) {
            $rows[] = [
                'store_id' => (int) $label->getStoreId(),
                'store_label' => (string) $label->getStoreLabel(),
            ];
        }
        return $rows;
    }
}
