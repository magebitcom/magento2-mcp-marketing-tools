<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Test\Unit\Tool\Marketing\CartRule;

use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpMarketingTools\Model\Search\CartRuleSearchBuilder;
use Magebit\McpMarketingTools\Tool\Marketing\CartRule\CartRuleList;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CartRuleListTest extends TestCase
{
    private CartRuleList $tool;

    protected function setUp(): void
    {
        $this->tool = new CartRuleList(
            $this->createMock(RuleRepositoryInterface::class),
            $this->createMock(CartRuleSearchBuilder::class),
            $this->createMock(ResolverPipeline::class),
            []
        );
    }

    public function testSchemaDeclaresTypedFilterKeys(): void
    {
        $schema = $this->tool->getInputSchema();
        $this->assertIsArray($schema['properties']);
        $filters = $schema['properties']['filters'];
        $this->assertIsArray($filters);
        $this->assertTrue($filters['additionalProperties']);
        $props = $filters['properties'];
        $this->assertIsArray($props);

        $expected = [
            'name' => 'string',
            'is_active' => 'boolean',
            'coupon_type' => 'string',
            'customer_group_id' => ['integer', 'array'],
            'website_id' => ['integer', 'array'],
            'from_date_after' => 'string',
            'to_date_before' => 'string',
        ];
        $this->assertSame(array_keys($expected), array_keys($props));
        foreach ($expected as $key => $type) {
            $prop = $props[$key];
            $this->assertIsArray($prop);
            $this->assertSame($type, $prop['type'], $key);
            $this->assertNotEmpty($prop['description'], $key);
        }
    }
}
