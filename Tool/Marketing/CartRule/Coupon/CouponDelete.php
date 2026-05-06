<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Tool\Marketing\CartRule\Coupon;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\CouponManagementInterface;

/**
 * Bulk-deletes coupons by id list or code list (exactly one).
 */
class CouponDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'marketing.cart_rule.coupon.delete';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_cart_rule_coupon_delete';

    /**
     * @param CouponManagementInterface $couponManagement
     */
    public function __construct(
        private readonly CouponManagementInterface $couponManagement
    ) {
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    /** @inheritDoc */
    public function getTitle(): string
    {
        return 'Delete Coupons';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Bulk-delete coupons by either coupon_ids[] OR codes[] '
            . '(exactly one). Returns failed_items[] (deletion failed) and '
            . 'missing_items[] (id/code not found). Existing orders that '
            . 'previously used a deleted code are unaffected.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->array('coupon_ids', fn (ArrayBuilder $a) => $a
                ->ofIntegers()
                ->description('Numeric coupon_ids to delete. Mutually exclusive with `codes`.'))
            ->array('codes', fn (ArrayBuilder $a) => $a
                ->ofStrings()
                ->description('Coupon codes to delete. Mutually exclusive with `coupon_ids`.'))
            ->boolean('ignore_invalid_coupons', fn (BooleanBuilder $b) => $b
                ->description('Skip rather than fail on invalid coupons (default true).'))
            ->toArray();
    }

    /** @inheritDoc */
    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /** @inheritDoc */
    public function getUnderlyingAclResource(): ?string
    {
        return 'Magento_SalesRule::quote';
    }

    /** @inheritDoc */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::WRITE;
    }

    /** @inheritDoc */
    public function getConfirmationRequired(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function execute(array $arguments): ToolResultInterface
    {
        $ids = $this->intList($arguments, 'coupon_ids');
        $codes = $this->stringList($arguments, 'codes');

        if ($ids === [] && $codes === []) {
            throw new LocalizedException(__('Provide either "coupon_ids" or "codes".'));
        }
        if ($ids !== [] && $codes !== []) {
            throw new LocalizedException(__('Provide only one of "coupon_ids" or "codes", not both.'));
        }

        $ignoreInvalid = !isset($arguments['ignore_invalid_coupons'])
            || (bool) $arguments['ignore_invalid_coupons'];

        if ($ids !== []) {
            $result = $this->couponManagement->deleteByIds($ids, $ignoreInvalid);
        } else {
            $result = $this->couponManagement->deleteByCodes($codes, $ignoreInvalid);
        }

        $failed = $result->getFailedItems() ?: [];
        $missing = $result->getMissingItems() ?: [];

        $requested = count($ids) + count($codes);
        $deleted = max(0, $requested - count($failed) - count($missing));
        $payload = [
            'requested' => $requested,
            'failed_items' => array_values($failed),
            'missing_items' => array_values($missing),
            'deleted' => $deleted,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode coupon-delete result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            isError: $failed !== [],
            auditSummary: [
                'requested' => $requested,
                'requested_ids' => $ids ?: null,
                'requested_codes' => $codes ?: null,
                'failed_items' => array_values($failed),
                'missing_items' => array_values($missing),
                'deleted_count' => $deleted,
            ]
        );
    }

    /**
     * @param array<string, mixed> $args
     * @param string $key
     * @return array<int, int>
     * @throws LocalizedException
     */
    private function intList(array $args, string $key): array
    {
        $raw = $args[$key] ?? null;
        if ($raw === null) {
            return [];
        }
        if (!is_array($raw)) {
            throw new LocalizedException(__('"%1" must be an array.', $key));
        }
        $out = [];
        foreach ($raw as $v) {
            if (!is_int($v) && !(is_string($v) && ctype_digit($v))) {
                throw new LocalizedException(__('"%1" values must be integers.', $key));
            }
            $out[] = (int) $v;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $args
     * @param string $key
     * @return array<int, string>
     * @throws LocalizedException
     */
    private function stringList(array $args, string $key): array
    {
        $raw = $args[$key] ?? null;
        if ($raw === null) {
            return [];
        }
        if (!is_array($raw)) {
            throw new LocalizedException(__('"%1" must be an array.', $key));
        }
        $out = [];
        foreach ($raw as $v) {
            if (!is_string($v) || $v === '') {
                throw new LocalizedException(__('"%1" values must be non-empty strings.', $key));
            }
            $out[] = $v;
        }
        return $out;
    }
}
