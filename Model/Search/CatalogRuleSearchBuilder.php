<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\Search;

use Magebit\McpMarketingTools\Api\CatalogRuleFilterTranslatorInterface;
use Magento\CatalogRule\Model\ResourceModel\Rule\Collection;
use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Builds the catalog-rule list collection.
 *
 * `CatalogRuleRepositoryInterface` has no `getList(SearchCriteria)`, so
 * listing goes through the same {@see Collection} the admin grid uses.
 * Filter translators that touch already-joined tables must use
 * {@see self::ensureJoinedOnce()} to avoid duplicate joins.
 */
class CatalogRuleSearchBuilder
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

    private const FLAG_PREFIX = 'mcp_marketing_join_';

    /**
     * @param CollectionFactory $collectionFactory
     * @param FilterValueCoercer $coercer
     * @param CatalogRuleFilterTranslatorInterface[] $filterTranslators
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly FilterValueCoercer $coercer,
        private readonly array $filterTranslators = []
    ) {
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return Collection
     * @throws LocalizedException
     */
    public function build(array $args): Collection
    {
        $collection = $this->collectionFactory->create();

        $filtersRaw = $args['filters'] ?? [];
        if (!is_array($filtersRaw)) {
            throw new LocalizedException(__('Filter payload must be an object.'));
        }

        foreach ($filtersRaw as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new LocalizedException(__('Filter keys must be non-empty strings.'));
            }
            $this->applyFilter($collection, $key, $value);
        }

        $this->applySort($collection, $args);
        $this->applyPaging($collection, $args);

        return $collection;
    }

    /**
     * Runs `$join` exactly once per `$tag` per collection instance.
     *
     * @param Collection $collection
     * @param string $tag
     * @param callable $join
     * @return void
     */
    public function ensureJoinedOnce(Collection $collection, string $tag, callable $join): void
    {
        $flag = self::FLAG_PREFIX . $tag;
        if ($collection->getFlag($flag) === true) {
            return;
        }
        $join($collection);
        $collection->setFlag($flag, true);
    }

    /**
     * @param Collection $collection
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function applyFilter(Collection $collection, string $key, mixed $value): void
    {
        switch ($key) {
            case 'name':
                if (!is_string($value) || $value === '') {
                    throw new LocalizedException(__('Filter "name" must be a non-empty string.'));
                }
                $pattern = '%' . $this->coercer->escapeLikeWildcards($value) . '%';
                $collection->addFieldToFilter('main_table.name', ['like' => $pattern]);
                return;

            case 'is_active':
                $collection->addFieldToFilter('main_table.is_active', (string) $this->coercer->boolToInt($value));
                return;

            case 'customer_group_id':
                if (!is_numeric($value) || (int) $value < 0) {
                    throw new LocalizedException(__('Filter "customer_group_id" must be a non-negative integer.'));
                }
                $this->ensureJoinedOnce(
                    $collection,
                    'catalogrule_customer_group',
                    static fn (Collection $c): mixed => $c->addCustomerGroupFilter((int) $value)
                );
                return;

            case 'website_id':
                $this->joinWebsiteFilter($collection, $value);
                return;

            case 'from_date_after':
                $collection->addFieldToFilter(
                    'main_table.from_date',
                    ['gteq' => $this->coercer->coerceIsoDate($value, $key)]
                );
                return;

            case 'to_date_before':
                $collection->addFieldToFilter(
                    'main_table.to_date',
                    ['lteq' => $this->coercer->coerceIsoDate($value, $key)]
                );
                return;
        }

        foreach ($this->filterTranslators as $translator) {
            if ($translator->supports($key)) {
                $translator->translate($key, $value, $collection);
                return;
            }
        }

        throw new LocalizedException(__('Unknown catalog rule filter: "%1".', $key));
    }

    /**
     * @param Collection $collection
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function joinWebsiteFilter(Collection $collection, mixed $value): void
    {
        $ids = $this->coercer->coerceIntList($value, 'website_id');
        if ($ids === []) {
            return;
        }
        $this->ensureJoinedOnce(
            $collection,
            'catalogrule_website',
            static function (Collection $c) use ($ids): void {
                $c->getSelect()->join(
                    ['mcp_cr_website' => $c->getResource()->getTable('catalogrule_website')],
                    'mcp_cr_website.rule_id = main_table.rule_id',
                    []
                );
                $c->getSelect()->where('mcp_cr_website.website_id IN (?)', $ids);
                $c->getSelect()->distinct(true);
            }
        );
    }

    /**
     * @param Collection $collection
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applySort(Collection $collection, array $args): void
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
        $collection->setOrder($sortBy, $dir === 'asc' ? 'ASC' : 'DESC');
    }

    /**
     * @param Collection $collection
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applyPaging(Collection $collection, array $args): void
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
        $collection->setCurPage($page);
        $collection->setPageSize($size);
    }
}
