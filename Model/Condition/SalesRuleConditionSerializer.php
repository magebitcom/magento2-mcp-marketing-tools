<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\Condition;

use Magento\SalesRule\Api\Data\ConditionInterface;

/**
 * Walks a sales-rule condition tree into nested JSON-friendly arrays.
 */
class SalesRuleConditionSerializer
{
    /**
     * @param ConditionInterface|null $node
     * @return array<string, mixed>|null
     */
    public function toJson(?ConditionInterface $node): ?array
    {
        if ($node === null) {
            return null;
        }

        $payload = [
            'condition_type' => (string) $node->getConditionType(),
            'attribute_name' => $node->getAttributeName() === null ? null : (string) $node->getAttributeName(),
            'operator' => (string) $node->getOperator(),
            'value' => $node->getValue(),
            'aggregator_type' => $node->getAggregatorType() === null ? null : (string) $node->getAggregatorType(),
        ];

        $children = $node->getConditions();
        if (is_array($children) && $children !== []) {
            $payload['conditions'] = array_values(array_map(
                fn (ConditionInterface $child): ?array => $this->toJson($child),
                $children
            ));
        }

        return $payload;
    }
}
