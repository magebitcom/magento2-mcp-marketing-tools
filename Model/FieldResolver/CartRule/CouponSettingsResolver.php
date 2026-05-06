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

/**
 * Coupon settings slice — coupon_type, use_auto_generation, uses_per_coupon,
 * uses_per_customer.
 */
class CouponSettingsResolver implements CartRuleFieldResolverInterface
{
    public const KEY = 'coupon_settings';

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
        return 60;
    }

    /**
     * @inheritDoc
     */
    public function resolve(RuleInterface $rule, array $args): array
    {
        unset($args);
        return [
            'coupon_type' => (string) $rule->getCouponType(),
            'use_auto_generation' => (bool) $rule->getUseAutoGeneration(),
            'uses_per_coupon' => (int) $rule->getUsesPerCoupon(),
            'uses_per_customer' => (int) $rule->getUsesPerCustomer(),
        ];
    }
}
