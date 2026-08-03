<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Test\Unit\Tool\Marketing\CatalogRule;

use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpMarketingTools\Model\Search\CatalogRuleSearchBuilder;
use Magebit\McpMarketingTools\Tool\Marketing\CatalogRule\CatalogRuleList;
use PHPUnit\Framework\TestCase;

class CatalogRuleListTest extends TestCase
{
    private CatalogRuleList $tool;

    protected function setUp(): void
    {
        $this->tool = new CatalogRuleList(
            $this->createMock(CatalogRuleSearchBuilder::class),
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
            'customer_group_id' => 'integer',
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
