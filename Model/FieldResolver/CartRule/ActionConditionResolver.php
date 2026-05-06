<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\FieldResolver\CartRule;

use Magebit\McpMarketingTools\Api\CartRuleFieldResolverInterface;
use Magebit\McpMarketingTools\Model\Condition\SalesRuleConditionSerializer;
use Magento\SalesRule\Api\Data\RuleInterface;
/**
 * `action_condition` slice — the "apply discount to which items" tree.
 */
class ActionConditionResolver implements CartRuleFieldResolverInterface
{
    public const KEY = 'action_condition';

    /**
     * @param SalesRuleConditionSerializer $serializer
     */
    public function __construct(
        private readonly SalesRuleConditionSerializer $serializer
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
        return 45;
    }

    /**
     * @inheritDoc
     */
    public function resolve(RuleInterface $rule, array $args): array
    {
        unset($args);
        return $this->serializer->toJson($rule->getActionCondition()) ?? [];
    }
}
