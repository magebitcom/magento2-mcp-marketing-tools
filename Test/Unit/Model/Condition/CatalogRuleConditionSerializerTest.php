<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Test\Unit\Model\Condition;

use Magebit\McpMarketingTools\Model\Condition\CatalogRuleConditionSerializer;
use Magento\CatalogRule\Api\Data\ConditionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CatalogRuleConditionSerializerTest extends TestCase
{
    private CatalogRuleConditionSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new CatalogRuleConditionSerializer();
    }

    public function testReturnsNullForNullInput(): void
    {
        $this->assertNull($this->serializer->toJson(null));
    }

    public function testSerializesLeafNode(): void
    {
        $leaf = $this->makeNode([
            'getType' => 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Product',
            'getAttribute' => 'sku',
            'getOperator' => '==',
            'getValue' => 'ABC-1',
            'getIsValueParsed' => null,
            'getAggregator' => '',
            'getConditions' => null,
        ]);

        $out = $this->serializer->toJson($leaf);

        $this->assertSame([
            'type' => 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Product',
            'attribute' => 'sku',
            'operator' => '==',
            'value' => 'ABC-1',
            'is_value_parsed' => null,
            'aggregator' => '',
        ], $out);
    }

    public function testSerializesNestedTree(): void
    {
        $leafA = $this->makeNode([
            'getType' => 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Product',
            'getAttribute' => 'sku',
            'getOperator' => '==',
            'getValue' => 'X',
            'getIsValueParsed' => null,
            'getAggregator' => '',
            'getConditions' => null,
        ]);
        $leafB = $this->makeNode([
            'getType' => 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Product',
            'getAttribute' => 'sku',
            'getOperator' => '==',
            'getValue' => 'Y',
            'getIsValueParsed' => null,
            'getAggregator' => '',
            'getConditions' => null,
        ]);
        $combine = $this->makeNode([
            'getType' => 'Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine',
            'getAttribute' => '',
            'getOperator' => '',
            'getValue' => '',
            'getIsValueParsed' => null,
            'getAggregator' => 'all',
            'getConditions' => [$leafA, $leafB],
        ]);

        $out = $this->serializer->toJson($combine);

        $this->assertNotNull($out);
        $this->assertSame('all', $out['aggregator']);
        $this->assertArrayHasKey('conditions', $out);
        $this->assertIsArray($out['conditions']);
        $this->assertCount(2, $out['conditions']);
        $this->assertIsArray($out['conditions'][0]);
        $this->assertIsArray($out['conditions'][1]);
        $this->assertSame('X', $out['conditions'][0]['value']);
        $this->assertSame('Y', $out['conditions'][1]['value']);
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
