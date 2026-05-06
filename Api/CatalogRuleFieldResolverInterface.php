<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Api;

use Magebit\Mcp\Api\FieldResolverInterface;
use Magento\CatalogRule\Model\Rule;

/**
 * Contributes a named slice to `marketing.catalog_rule.{list,get}` output.
 *
 * Resolvers receive the concrete {@see Rule} model (not the API DTO) because
 * `from_date` / `to_date` / `customer_group_ids` / `website_ids` aren't on
 * the API interface.
 */
interface CatalogRuleFieldResolverInterface extends FieldResolverInterface
{
    /**
     * @param Rule $rule
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int|string, mixed>
     */
    public function resolve(Rule $rule, array $args): array;
}
