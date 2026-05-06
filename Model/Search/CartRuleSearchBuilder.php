<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\Search;

use Magebit\McpMarketingTools\Api\CartRuleFilterTranslatorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\Data\RuleInterface;

/**
 * Builds SearchCriteria for {@see \Magento\SalesRule\Api\RuleRepositoryInterface::getList()}.
 *
 * `customer_group_id` / `website_id` are pre-resolved against the
 * `salesrule_*` association tables and emitted as a `rule_id IN (...)` filter,
 * because the API DTO virtual fields are populated only in `_afterLoad`.
 */
class CartRuleSearchBuilder
{
    public const MAX_PAGE_SIZE = 200;
    public const DEFAULT_PAGE_SIZE = 50;

    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        'sort_order',
        'name',
        'rule_id',
        'is_active',
        'from_date',
        'to_date',
    ];

    /** @var array<int, string> */
    private const COUPON_TYPES = [
        RuleInterface::COUPON_TYPE_NO_COUPON,
        RuleInterface::COUPON_TYPE_SPECIFIC_COUPON,
        RuleInterface::COUPON_TYPE_AUTO,
    ];

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     * @param ResourceConnection $resource
     * @param FilterValueCoercer $coercer
     * @param CartRuleFilterTranslatorInterface[] $filterTranslators
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly SortOrderBuilder $sortBuilder,
        private readonly ResourceConnection $resource,
        private readonly FilterValueCoercer $coercer,
        private readonly array $filterTranslators = []
    ) {
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return SearchCriteriaInterface
     * @throws LocalizedException
     */
    public function build(array $args): SearchCriteriaInterface
    {
        $filtersRaw = $args['filters'] ?? [];
        if (!is_array($filtersRaw)) {
            throw new LocalizedException(__('Filter payload must be an object.'));
        }

        foreach ($filtersRaw as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new LocalizedException(__('Filter keys must be non-empty strings.'));
            }
            $this->applyFilter($key, $value);
        }

        $this->applySort($args);
        $this->applyPaging($args);

        return $this->criteriaBuilder->create();
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function applyFilter(string $key, mixed $value): void
    {
        switch ($key) {
            case 'name':
                if (!is_string($value) || $value === '') {
                    throw new LocalizedException(__('Filter "name" must be a non-empty string.'));
                }
                $pattern = '%' . $this->coercer->escapeLikeWildcards($value) . '%';
                $this->criteriaBuilder->addFilter('name', $pattern, 'like');
                return;

            case 'is_active':
                $this->criteriaBuilder->addFilter('is_active', $this->coercer->boolToInt($value));
                return;

            case 'coupon_type':
                if (!is_string($value) || !in_array($value, self::COUPON_TYPES, true)) {
                    throw new LocalizedException(__(
                        'Filter "coupon_type" must be one of: %1.',
                        implode(', ', self::COUPON_TYPES)
                    ));
                }
                $this->criteriaBuilder->addFilter('coupon_type', $value);
                return;

            case 'customer_group_id':
                $this->addAssociationFilter('salesrule_customer_group', 'customer_group_id', $value);
                return;

            case 'website_id':
                $this->addAssociationFilter('salesrule_website', 'website_id', $value);
                return;

            case 'from_date_after':
                $this->criteriaBuilder->addFilter('from_date', $this->coercer->coerceIsoDate($value, $key), 'gteq');
                return;

            case 'to_date_before':
                $this->criteriaBuilder->addFilter('to_date', $this->coercer->coerceIsoDate($value, $key), 'lteq');
                return;
        }

        foreach ($this->filterTranslators as $translator) {
            if ($translator->supports($key)) {
                $translator->translate($key, $value, $this->criteriaBuilder);
                return;
            }
        }

        throw new LocalizedException(__('Unknown cart rule filter: "%1".', $key));
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applySort(array $args): void
    {
        $sortBy = $args['sort_by'] ?? 'sort_order';
        if (!is_string($sortBy) || !in_array($sortBy, self::SORTABLE_FIELDS, true)) {
            throw new LocalizedException(__(
                '"sort_by" must be one of: %1.',
                implode(', ', self::SORTABLE_FIELDS)
            ));
        }
        $dirRaw = $args['sort_dir'] ?? 'asc';
        $dir = is_string($dirRaw) ? strtolower($dirRaw) : 'asc';
        if ($dir !== 'asc' && $dir !== 'desc') {
            throw new LocalizedException(__('"sort_dir" must be "asc" or "desc".'));
        }
        $this->sortBuilder->setField($sortBy);
        $this->sortBuilder->setDirection($dir === 'asc' ? SortOrder::SORT_ASC : SortOrder::SORT_DESC);
        $this->criteriaBuilder->addSortOrder($this->sortBuilder->create());
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applyPaging(array $args): void
    {
        $page = isset($args['page']) && is_numeric($args['page']) ? max(1, (int) $args['page']) : 1;
        $sizeRaw = $args['page_size'] ?? self::DEFAULT_PAGE_SIZE;
        if (!is_numeric($sizeRaw)) {
            throw new LocalizedException(__('"page_size" must be numeric.'));
        }
        $size = max(1, (int) $sizeRaw);
        if ($size > self::MAX_PAGE_SIZE) {
            $size = self::MAX_PAGE_SIZE;
        }
        $this->criteriaBuilder->setCurrentPage($page);
        $this->criteriaBuilder->setPageSize($size);
    }

    /**
     * @param string $table
     * @param string $column
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addAssociationFilter(string $table, string $column, mixed $value): void
    {
        $ids = $this->coercer->coerceIntList($value, $column);
        if ($ids === []) {
            return;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['t' => $this->resource->getTableName($table)], 'rule_id')
            ->where("t.{$column} IN (?)", $ids);
        $matchingRuleIds = array_values(array_unique(array_map('intval', $connection->fetchCol($select))));
        if ($matchingRuleIds === []) {
            $this->criteriaBuilder->addFilter('rule_id', 0);
            return;
        }
        $this->criteriaBuilder->addFilter('rule_id', $matchingRuleIds, 'in');
    }
}
