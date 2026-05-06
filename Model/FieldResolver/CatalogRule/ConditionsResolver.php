<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\FieldResolver\CatalogRule;

use Magebit\McpMarketingTools\Api\CatalogRuleFieldResolverInterface;
use Magebit\McpMarketingTools\Model\Condition\CatalogRuleConditionSerializer;
use Magento\CatalogRule\Model\Rule;
/**
 * `conditions` slice — recursive condition tree.
 */
class ConditionsResolver implements CatalogRuleFieldResolverInterface
{
    public const KEY = 'conditions';

    /**
     * @param CatalogRuleConditionSerializer $serializer
     */
    public function __construct(
        private readonly CatalogRuleConditionSerializer $serializer
    ) {
    }

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
        return 40;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Rule $rule, array $args): array
    {
        unset($args);
        return $this->serializer->toJson($rule->getRuleCondition()) ?? [];
    }
}
