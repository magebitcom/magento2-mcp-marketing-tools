<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\Search;

use Magebit\McpMarketingTools\Api\CouponFilterTranslatorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\Data\CouponInterface;

/**
 * Builds SearchCriteria for {@see \Magento\SalesRule\Api\CouponRepositoryInterface::getList()}.
 */
class CouponSearchBuilder
{
    public const MAX_PAGE_SIZE = 500;
    public const DEFAULT_PAGE_SIZE = 100;

    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        'created_at',
        'code',
        'times_used',
        'usage_limit',
        'expiration_date',
    ];

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     * @param FilterValueCoercer $coercer
     * @param CouponFilterTranslatorInterface[] $filterTranslators
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly SortOrderBuilder $sortBuilder,
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
            case 'rule_id':
                $this->addEqualsOrIn('rule_id', $value);
                return;

            case 'code':
                if (!is_string($value) || $value === '') {
                    throw new LocalizedException(__('Filter "code" must be a non-empty string.'));
                }
                $pattern = '%' . $this->coercer->escapeLikeWildcards($value) . '%';
                $this->criteriaBuilder->addFilter('code', $pattern, 'like');
                return;

            case 'type':
                $this->criteriaBuilder->addFilter('type', $this->coerceType($value));
                return;

            case 'is_primary':
                $this->criteriaBuilder->addFilter('is_primary', $this->coercer->boolToInt($value));
                return;

            case 'created_after':
                $this->criteriaBuilder->addFilter(
                    'created_at',
                    $this->coercer->coerceIsoDate($value, $key),
                    'gteq'
                );
                return;
        }

        foreach ($this->filterTranslators as $translator) {
            if ($translator->supports($key)) {
                $translator->translate($key, $value, $this->criteriaBuilder);
                return;
            }
        }

        throw new LocalizedException(__('Unknown coupon filter: "%1".', $key));
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applySort(array $args): void
    {
        $sortBy = $args['sort_by'] ?? 'created_at';
        if (!is_string($sortBy) || !in_array($sortBy, self::SORTABLE_FIELDS, true)) {
            throw new LocalizedException(__(
                '"sort_by" must be one of: %1.',
                implode(', ', self::SORTABLE_FIELDS)
            ));
        }
        $dirRaw = $args['sort_dir'] ?? 'desc';
        $dir = is_string($dirRaw) ? strtolower($dirRaw) : 'desc';
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
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addEqualsOrIn(string $field, mixed $value): void
    {
        if (is_array($value)) {
            $list = array_values(array_filter(
                $value,
                static fn($v): bool => is_scalar($v) && (string) $v !== ''
            ));
            if ($list === []) {
                return;
            }
            $this->criteriaBuilder->addFilter($field, $list, 'in');
            return;
        }
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "%1" requires a non-empty value.', $field));
        }
        $this->criteriaBuilder->addFilter($field, $value);
    }

    /**
     * @param mixed $value
     * @return int
     * @throws LocalizedException
     */
    private function coerceType(mixed $value): int
    {
        if (is_string($value)) {
            $upper = strtoupper($value);
            if ($upper === 'MANUAL') {
                return CouponInterface::TYPE_MANUAL;
            }
            if ($upper === 'GENERATED') {
                return CouponInterface::TYPE_GENERATED;
            }
        }
        if (is_int($value) && in_array($value, [CouponInterface::TYPE_MANUAL, CouponInterface::TYPE_GENERATED], true)) {
            return $value;
        }
        throw new LocalizedException(__('Filter "type" must be "MANUAL" or "GENERATED".'));
    }
}
