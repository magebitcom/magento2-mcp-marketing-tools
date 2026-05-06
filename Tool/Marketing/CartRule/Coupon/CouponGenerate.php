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
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\CouponManagementInterface;
use Magento\SalesRule\Api\Data\CouponGenerationSpecInterface;
use Magento\SalesRule\Api\Data\CouponGenerationSpecInterfaceFactory;

/**
 * Bulk-generates coupon codes. The rule must have
 * `coupon_type = SPECIFIC_COUPON` and `use_auto_generation = true`.
 */
class CouponGenerate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'marketing.cart_rule.coupon.generate';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_cart_rule_coupon_generate';

    private const DEFAULT_LENGTH = 12;

    /** @var array<int, string> */
    private const FORMATS = [
        CouponGenerationSpecInterface::COUPON_FORMAT_ALPHANUMERIC,
        CouponGenerationSpecInterface::COUPON_FORMAT_ALPHABETICAL,
        CouponGenerationSpecInterface::COUPON_FORMAT_NUMERIC,
    ];

    /**
     * @param CouponManagementInterface $couponManagement
     * @param CouponGenerationSpecInterfaceFactory $specFactory
     */
    public function __construct(
        private readonly CouponManagementInterface $couponManagement,
        private readonly CouponGenerationSpecInterfaceFactory $specFactory
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
        return 'Generate Coupon Codes';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Bulk-generate coupon codes for a cart price rule. The rule '
            . 'must have coupon_type=SPECIFIC_COUPON and use_auto_generation '
            . 'enabled. Format=alphanum|alpha|num.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('rule_id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->required()
                ->description('Cart-rule id to generate codes for.'))
            ->integer('quantity', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(1000)
                ->required()
                ->description('Number of unique codes to generate (1-1000).'))
            ->integer('length', fn (IntegerBuilder $i) => $i
                ->minimum(6)
                ->maximum(32)
                ->description('Code length excluding prefix/suffix (6-32, default 12).'))
            ->string('format', fn (StringBuilder $s) => $s
                ->enum(self::FORMATS)
                ->description('Code alphabet (default alphanum).'))
            ->string('prefix', fn (StringBuilder $s) => $s
                ->maxLength(32)
                ->description('Optional fixed prefix prepended to every code.'))
            ->string('suffix', fn (StringBuilder $s) => $s
                ->maxLength(32)
                ->description('Optional fixed suffix appended to every code.'))
            ->integer('delimiter_at_every', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(16)
                ->description('Insert delimiter every N characters.'))
            ->string('delimiter', fn (StringBuilder $s) => $s
                ->maxLength(4)
                ->description('Delimiter string (only used if delimiter_at_every is set).'))
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
        $ruleId = $this->intArg($arguments, 'rule_id');
        $quantity = $this->intArg($arguments, 'quantity');
        $length = isset($arguments['length']) && is_numeric($arguments['length'])
            ? (int) $arguments['length']
            : self::DEFAULT_LENGTH;

        $spec = $this->specFactory->create();
        $spec->setRuleId($ruleId);
        $spec->setQuantity($quantity);
        $spec->setLength($length);
        $spec->setFormat(
            isset($arguments['format']) && is_string($arguments['format'])
                ? $arguments['format']
                : CouponGenerationSpecInterface::COUPON_FORMAT_ALPHANUMERIC
        );
        if (isset($arguments['prefix']) && is_string($arguments['prefix']) && $arguments['prefix'] !== '') {
            $spec->setPrefix($arguments['prefix']);
        }
        if (isset($arguments['suffix']) && is_string($arguments['suffix']) && $arguments['suffix'] !== '') {
            $spec->setSuffix($arguments['suffix']);
        }
        if (isset($arguments['delimiter_at_every']) && is_numeric($arguments['delimiter_at_every'])) {
            $spec->setDelimiterAtEvery((int) $arguments['delimiter_at_every']);
        }
        if (isset($arguments['delimiter']) && is_string($arguments['delimiter']) && $arguments['delimiter'] !== '') {
            $spec->setDelimiter($arguments['delimiter']);
        }

        $codes = array_values($this->couponManagement->generate($spec));

        $payload = [
            'rule_id' => $ruleId,
            'generated' => count($codes),
            'codes' => $codes,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode coupon-generate result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'rule_id' => $ruleId,
                'requested_quantity' => $quantity,
                'generated_count' => count($codes),
                'first_code' => $codes[0] ?? null,
                'last_code' => $codes !== [] ? $codes[count($codes) - 1] : null,
            ]
        );
    }

    /**
     * @param array<string, mixed> $args
     * @param string $key
     * @return int
     * @throws LocalizedException
     */
    private function intArg(array $args, string $key): int
    {
        $value = $args[$key] ?? null;
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }
        throw new LocalizedException(__('"%1" must be a positive integer.', $key));
    }
}
