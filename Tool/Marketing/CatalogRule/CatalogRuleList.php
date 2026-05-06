<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Tool\Marketing\CatalogRule;

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
use Magebit\McpMarketingTools\Api\CatalogRuleFieldResolverInterface;
use Magebit\McpMarketingTools\Model\Search\CatalogRuleSearchBuilder;
use Magento\CatalogRule\Model\Rule;
use Magento\Framework\Exception\LocalizedException;

class CatalogRuleList implements ToolInterface
{
    public const TOOL_NAME = 'marketing.catalog_rule.list';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_catalog_rule_list';

    /**
     * @param CatalogRuleSearchBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param CatalogRuleFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly CatalogRuleSearchBuilder $searchBuilder,
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
        return 'List Catalog Rules';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Search catalog price rules with optional filters (name, '
            . 'is_active, customer_group_id, website_id, from_date_after, '
            . 'to_date_before) and paging. Each result row is composed from '
            . 'the same field resolvers as `marketing.catalog_rule.get`; '
            . 'use `fields` / `exclude` to narrow.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->with(Filters::describing(
                'Filter clauses. Built-in keys: name (substring), is_active, '
                . 'customer_group_id, website_id (scalar or array), '
                . 'from_date_after, to_date_before.'
            ))
            ->with(Sort::fields(CatalogRuleSearchBuilder::SORTABLE_FIELDS, 'sort_order', 'asc'))
            ->with(Pagination::maxPageSize(CatalogRuleSearchBuilder::MAX_PAGE_SIZE))
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
        $collection = $this->searchBuilder->build($arguments);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($collection->getItems() as $rule) {
            if (!$rule instanceof Rule) {
                continue;
            }
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($rule, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $collection->getSize(),
            'page' => (int) $collection->getCurPage(),
            'page_size' => (int) $collection->getPageSize(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode catalog-rule list as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'row_count' => count($rows),
                'total_count' => (int) $collection->getSize(),
                'page' => (int) $collection->getCurPage(),
                'page_size' => (int) $collection->getPageSize(),
            ]
        );
    }
}
