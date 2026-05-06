<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Api;

use Magento\CatalogRule\Model\ResourceModel\Rule\Collection;

/**
 * Extension point for `marketing.catalog_rule.list` filter keys not handled
 * by {@see \Magebit\McpMarketingTools\Model\Search\CatalogRuleSearchBuilder}.
 *
 * Catalog rules have no `getList(SearchCriteria)` API, so translators receive
 * the loaded {@see Collection} directly.
 */
interface CatalogRuleFilterTranslatorInterface
{
    /**
     * @param string $key
     * @return bool
     */
    public function supports(string $key): bool;

    /**
     * @param string $key
     * @param mixed $value
     * @param Collection $collection
     * @return void
     */
    public function translate(string $key, mixed $value, Collection $collection): void;
}
