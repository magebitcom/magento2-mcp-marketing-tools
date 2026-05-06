<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\FieldResolver\Coupon;

use Magebit\McpMarketingTools\Api\CouponFieldResolverInterface;
use Magento\SalesRule\Api\Data\CouponInterface;

/**
 * Rule slice — the cart-rule id this coupon belongs to.
 */
class RuleResolver implements CouponFieldResolverInterface
{
    public const KEY = 'rule';

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
    public function resolve(CouponInterface $coupon, array $args): array
    {
        unset($args);
        return [
            'rule_id' => (int) $coupon->getRuleId(),
        ];
    }
}
