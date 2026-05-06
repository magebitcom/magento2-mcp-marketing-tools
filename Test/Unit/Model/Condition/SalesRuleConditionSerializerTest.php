<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Test\Unit\Model\Condition;

use Magebit\McpMarketingTools\Model\Condition\SalesRuleConditionSerializer;
use Magento\SalesRule\Api\Data\ConditionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SalesRuleConditionSerializerTest extends TestCase
{
    private SalesRuleConditionSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new SalesRuleConditionSerializer();
    }

    public function testReturnsNullForNullInput(): void
    {
        $this->assertNull($this->serializer->toJson(null));
    }

    public function testSerializesLeafNode(): void
    {
        $leaf = $this->makeNode([
            'getConditionType' => 'Magento\\SalesRule\\Model\\Rule\\Condition\\Address',
            'getAttributeName' => 'base_subtotal',
            'getOperator' => '>=',
            'getValue' => '100',
            'getAggregatorType' => null,
            'getConditions' => null,
        ]);

        $out = $this->serializer->toJson($leaf);

        $this->assertSame([
            'condition_type' => 'Magento\\SalesRule\\Model\\Rule\\Condition\\Address',
            'attribute_name' => 'base_subtotal',
            'operator' => '>=',
            'value' => '100',
            'aggregator_type' => null,
        ], $out);
    }

    public function testSerializesNestedTreeThreeDeep(): void
    {
        $deepLeaf = $this->makeNode([
            'getConditionType' => 'Magento\\SalesRule\\Model\\Rule\\Condition\\Product',
            'getAttributeName' => 'category_ids',
            'getOperator' => '==',
            'getValue' => '7',
            'getAggregatorType' => null,
            'getConditions' => null,
        ]);
        $innerCombine = $this->makeNode([
            'getConditionType' => 'Magento\\SalesRule\\Model\\Rule\\Condition\\Product\\Found',
            'getAttributeName' => null,
            'getOperator' => '1',
            'getValue' => '1',
            'getAggregatorType' => 'all',
            'getConditions' => [$deepLeaf],
        ]);
        $rootCombine = $this->makeNode([
            'getConditionType' => 'Magento\\SalesRule\\Model\\Rule\\Condition\\Combine',
            'getAttributeName' => null,
            'getOperator' => '1',
            'getValue' => '1',
            'getAggregatorType' => 'all',
            'getConditions' => [$innerCombine],
        ]);

        $out = $this->serializer->toJson($rootCombine);

        $this->assertNotNull($out);
        $this->assertSame('all', $out['aggregator_type']);
        $this->assertArrayHasKey('conditions', $out);
        $this->assertIsArray($out['conditions']);
        $this->assertCount(1, $out['conditions']);
        $inner = $out['conditions'][0];
        $this->assertIsArray($inner);
        $this->assertSame('all', $inner['aggregator_type']);
        $this->assertIsArray($inner['conditions']);
        $deepLeafOut = $inner['conditions'][0];
        $this->assertIsArray($deepLeafOut);
        $this->assertSame('category_ids', $deepLeafOut['attribute_name']);
    }

    /**
     * @param array<string, mixed> $methods
     * @return ConditionInterface&MockObject
     */
    private function makeNode(array $methods): ConditionInterface
    {
        /** @var ConditionInterface&MockObject $node */
        $node = $this->createMock(ConditionInterface::class);
        foreach ($methods as $method => $value) {
            $node->method($method)->willReturn($value);
        }
        return $node;
    }
}
