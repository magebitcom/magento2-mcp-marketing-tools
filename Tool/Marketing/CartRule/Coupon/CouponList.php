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
use Magebit\Mcp\Model\Tool\Schema\Preset\FieldSelection;
use Magebit\Mcp\Model\Tool\Schema\Preset\Filters;
use Magebit\Mcp\Model\Tool\Schema\Preset\Pagination;
use Magebit\Mcp\Model\Tool\Schema\Preset\Sort;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpMarketingTools\Api\CouponFieldResolverInterface;
use Magebit\McpMarketingTools\Model\Search\CouponSearchBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\CouponRepositoryInterface;

class CouponList implements ToolInterface
{
    public const TOOL_NAME = 'marketing.cart_rule.coupon.list';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_cart_rule_coupon_list';

    /**
     * @param CouponRepositoryInterface $couponRepository
     * @param CouponSearchBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param CouponFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly CouponRepositoryInterface $couponRepository,
        private readonly CouponSearchBuilder $searchBuilder,
        private readonly ResolverPipeline $pipeline,
        private readonly array $fieldResolvers = []
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
        return 'List Coupons';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Search coupon codes across cart price rules. Built-in filters: '
            . 'rule_id (scope to one rule), code (substring), type '
            . '(MANUAL|GENERATED), is_primary, created_after.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->with(Filters::describing(
                'Filter clauses. `rule_id` accepts a scalar or array (⇒ IN); '
                . 'the other keys take a single scalar.',
                [
                    'rule_id' => ['type' => ['integer', 'array'], 'description' => 'Parent cart-rule id(s) to scope coupons to.'],
                    'code' => ['type' => 'string', 'description' => 'Substring match on coupon code.'],
                    'type' => ['type' => 'string', 'description' => 'MANUAL or GENERATED.'],
                    'is_primary' => ['type' => 'boolean', 'description' => 'Only the rule\'s primary coupon when true.'],
                    'created_after' => ['type' => 'string', 'description' => 'Created on/after (ISO date).'],
                ]
            ))
            ->with(Sort::fields(CouponSearchBuilder::SORTABLE_FIELDS, 'created_at', 'desc'))
            ->with(Pagination::maxPageSize(CouponSearchBuilder::MAX_PAGE_SIZE))
            ->with(FieldSelection::default())
            ->toArray();
    }

    /** @inheritDoc */
    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /** @inheritDoc */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::READ;
    }

    /** @inheritDoc */
    public function getConfirmationRequired(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function execute(array $arguments): ToolResultInterface
    {
        $criteria = $this->searchBuilder->build($arguments);
        $result = $this->couponRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $coupon) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($coupon, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? CouponSearchBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode coupon list as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'row_count' => count($rows),
                'total_count' => (int) $result->getTotalCount(),
                'page' => $criteria->getCurrentPage(),
                'page_size' => $criteria->getPageSize(),
            ]
        );
    }
}
