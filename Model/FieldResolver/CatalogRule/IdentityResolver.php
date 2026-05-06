<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\FieldResolver\CatalogRule;

use Magebit\McpMarketingTools\Api\CatalogRuleFieldResolverInterface;
use Magento\CatalogRule\Model\Rule;

/**
 * Identity slice — rule_id, name, description.
 */
class IdentityResolver implements CatalogRuleFieldResolverInterface
{
    public const KEY = 'identity';

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 10;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Rule $rule, array $args): array
    {
        unset($args);
        return [
            'rule_id' => (int) $rule->getRuleId(),
            'name' => (string) $rule->getName(),
            'description' => $rule->getDescription() === null ? null : (string) $rule->getDescription(),
        ];
    }
}
