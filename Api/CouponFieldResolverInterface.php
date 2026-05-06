<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Api;

use Magebit\Mcp\Api\FieldResolverInterface;
use Magento\SalesRule\Api\Data\CouponInterface;

/**
 * Contract for a contributor to `marketing.cart_rule.coupon.list` /
 * `marketing.cart_rule.coupon.get` output.
 */
interface CouponFieldResolverInterface extends FieldResolverInterface
{
    /**
     * @param CouponInterface $coupon
     * @param array $args Raw tool-call arguments.
     * @phpstan-param array<string, mixed> $args
     * @return array<int|string, mixed>
     */
    public function resolve(CouponInterface $coupon, array $args): array;
}
