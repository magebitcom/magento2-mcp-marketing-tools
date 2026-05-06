<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Api;

use Magebit\Mcp\Api\FieldResolverInterface;
use Magento\SalesRule\Api\Data\RuleInterface;

/**
 * Contributes a named slice to `marketing.cart_rule.{list,get}` output.
 */
interface CartRuleFieldResolverInterface extends FieldResolverInterface
{
    /**
     * @param RuleInterface $rule
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int|string, mixed>
     */
    public function resolve(RuleInterface $rule, array $args): array;
}
