<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\FieldResolver\CatalogRule;

use Magebit\McpMarketingTools\Api\CatalogRuleFieldResolverInterface;
use Magento\CatalogRule\Model\Rule;

/**
 * Scope slice — website_ids, customer_group_ids (populated via the model's
 * `_afterLoad()` from the catalogrule_website / _customer_group tables).
 */
class ScopeResolver implements CatalogRuleFieldResolverInterface
{
    public const KEY = 'scope';

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
        return 20;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Rule $rule, array $args): array
    {
        unset($args);
        return [
            'website_ids' => $this->intIds($rule->getWebsiteIds()),
            'customer_group_ids' => $this->intIds($rule->getCustomerGroupIds()),
        ];
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function intIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = $raw === '' ? [] : explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_numeric($v)) {
                $out[] = (int) $v;
            }
        }
        return $out;
    }
}
