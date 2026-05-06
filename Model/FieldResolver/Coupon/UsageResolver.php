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
 * Usage slice — usage_limit, usage_per_customer, times_used, expiration_date.
 */
class UsageResolver implements CouponFieldResolverInterface
{
    public const KEY = 'usage';

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
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function resolve(CouponInterface $coupon, array $args): array
    {
        unset($args);
        $limit = $coupon->getUsageLimit();
        $perCustomer = $coupon->getUsagePerCustomer();
        $expires = $coupon->getExpirationDate();
        return [
            'usage_limit' => $limit === null ? null : (int) $limit,
            'usage_per_customer' => $perCustomer === null ? null : (int) $perCustomer,
            'times_used' => (int) $coupon->getTimesUsed(),
            'expiration_date' => $expires === null || $expires === '' ? null : (string) $expires,
        ];
    }
}
