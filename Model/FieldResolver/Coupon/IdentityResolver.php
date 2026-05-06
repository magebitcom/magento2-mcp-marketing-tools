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

class IdentityResolver implements CouponFieldResolverInterface
{
    public const KEY = 'identity';

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
        return 10;
    }

    /**
     * @inheritDoc
     */
    public function resolve(CouponInterface $coupon, array $args): array
    {
        unset($args);
        $type = $coupon->getType();
        return [
            'coupon_id' => (int) $coupon->getCouponId(),
            'code' => $coupon->getCode() === null ? null : (string) $coupon->getCode(),
            'type' => $type === null ? null : $this->typeLabel((int) $type),
            'is_primary' => (bool) $coupon->getIsPrimary(),
        ];
    }

    /**
     * @param int $type
     * @return string
     */
    private function typeLabel(int $type): string
    {
        return $type === CouponInterface::TYPE_GENERATED ? 'GENERATED' : 'MANUAL';
    }
}
