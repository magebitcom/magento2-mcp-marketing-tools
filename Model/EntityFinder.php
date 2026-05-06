<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model;

use Magento\CatalogRule\Api\CatalogRuleRepositoryInterface;
use Magento\CatalogRule\Model\Rule as CatalogRule;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\RuleInterface as CartRuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface as CartRuleRepositoryInterface;

/**
 * Shared id-based lookups for promotion entities. Missing ids raise
 * {@see LocalizedException}; not-found surfaces via the same type
 * (`NoSuchEntityException` descends from it).
 */
class EntityFinder
{
    /**
     * @param CatalogRuleRepositoryInterface $catalogRuleRepository
     * @param CartRuleRepositoryInterface $cartRuleRepository
     * @param CouponRepositoryInterface $couponRepository
     */
    public function __construct(
        private readonly CatalogRuleRepositoryInterface $catalogRuleRepository,
        private readonly CartRuleRepositoryInterface $cartRuleRepository,
        private readonly CouponRepositoryInterface $couponRepository
    ) {
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return CatalogRule
     * @throws LocalizedException
     */
    public function catalogRuleFrom(array $args): CatalogRule
    {
        $id = $this->requireNumeric($args, 'rule_id');

        try {
            $rule = $this->catalogRuleRepository->get($id);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Catalog rule %1 not found.', $id), $e);
        }

        if (!$rule instanceof CatalogRule) {
            throw new LocalizedException(__('Catalog rule %1 returned an unexpected concrete type.', $id));
        }
        return $rule;
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return CartRuleInterface
     * @throws LocalizedException
     */
    public function cartRuleFrom(array $args): CartRuleInterface
    {
        $id = $this->requireNumeric($args, 'rule_id');

        try {
            return $this->cartRuleRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Cart price rule %1 not found.', $id), $e);
        }
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return CouponInterface
     * @throws LocalizedException
     */
    public function couponFrom(array $args): CouponInterface
    {
        $id = $this->requireNumeric($args, 'coupon_id');

        try {
            return $this->couponRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Coupon %1 not found.', $id), $e);
        }
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @param string $key
     * @return int
     * @throws LocalizedException
     */
    private function requireNumeric(array $args, string $key): int
    {
        if (!array_key_exists($key, $args)) {
            throw new LocalizedException(__('"%1" is required.', $key));
        }
        $value = $args[$key];
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }
        throw new LocalizedException(__('"%1" must be a positive integer.', $key));
    }
}
