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
 * Lifecycle slice — created_at.
 */
class LifecycleResolver implements CouponFieldResolverInterface
{
    public const KEY = 'lifecycle';

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
        return 40;
    }

    /**
     * @inheritDoc
     */
    public function resolve(CouponInterface $coupon, array $args): array
    {
        unset($args);
        $created = $coupon->getCreatedAt();
        return [
            'created_at' => $created === null || $created === '' ? null : (string) $created,
        ];
    }
}
