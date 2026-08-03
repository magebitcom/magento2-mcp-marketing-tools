<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Tool\Marketing\CartRule;

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
use Magebit\McpMarketingTools\Api\CartRuleFieldResolverInterface;
use Magebit\McpMarketingTools\Model\Search\CartRuleSearchBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\RuleRepositoryInterface;

class CartRuleList implements ToolInterface
{
    public const TOOL_NAME = 'marketing.cart_rule.list';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_cart_rule_list';

    /**
     * @param RuleRepositoryInterface $cartRuleRepository
     * @param CartRuleSearchBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param CartRuleFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly RuleRepositoryInterface $cartRuleRepository,
        private readonly CartRuleSearchBuilder $searchBuilder,
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
        return 'List Cart Price Rules';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Search cart price rules with optional filters (name, '
            . 'is_active, coupon_type, customer_group_id, website_id, '
            . 'from_date_after, to_date_before) and paging. Each result row '
            . 'is composed from the same field resolvers as '
            . '`marketing.cart_rule.get`; use `fields` / `exclude` to narrow.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->with(Filters::describing(
                'Filter clauses. `customer_group_id` / `website_id` accept a '
                . 'scalar or array (⇒ IN); the other keys take a single scalar.',
                [
                    'name' => ['type' => 'string', 'description' => 'Substring match on rule name.'],
                    'is_active' => ['type' => 'boolean', 'description' => 'Active (true) or inactive (false).'],
                    'coupon_type' => ['type' => 'string', 'description' => 'One of NO_COUPON, SPECIFIC_COUPON, AUTO.'],
                    'customer_group_id' => ['type' => ['integer', 'array'], 'description' => 'Customer group id(s) the rule applies to.'],
                    'website_id' => ['type' => ['integer', 'array'], 'description' => 'Website id(s) the rule applies to.'],
                    'from_date_after' => ['type' => 'string', 'description' => 'Rule start date on/after (ISO date).'],
                    'to_date_before' => ['type' => 'string', 'description' => 'Rule end date on/before (ISO date).'],
                ]
            ))
            ->with(Sort::fields(CartRuleSearchBuilder::SORTABLE_FIELDS, 'sort_order', 'asc'))
            ->with(Pagination::maxPageSize(CartRuleSearchBuilder::MAX_PAGE_SIZE))
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
        $result = $this->cartRuleRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $rule) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($rule, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? CartRuleSearchBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode cart-rule list as JSON.'));
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
