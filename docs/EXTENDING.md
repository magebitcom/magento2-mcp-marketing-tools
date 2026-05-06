# Extending Magebit_McpMarketingTools

Two extension points are first-class:

1. Field resolvers — add a named slice to a tool's read response.
2. Filter translators — add a new key to a `list` tool's `filters` argument.

A custom write tool follows the same pattern as the shipped writes — see the
ACL section at the bottom of this file.

## Adding a field resolver

A field resolver owns one named slice of the response (e.g. `identity`,
`scope`, `conditions`). Implement the matching interface and register the
resolver under the tool's `fieldResolvers` argument.

### Example — add a "summary" slice to `marketing.cart_rule.get`

```php
// Vendor/Module/Model/CartRuleSummaryResolver.php
namespace Vendor\Module\Model;

use Magebit\McpMarketingTools\Api\CartRuleFieldResolverInterface;
use Magento\SalesRule\Api\Data\RuleInterface;

class CartRuleSummaryResolver implements CartRuleFieldResolverInterface
{
    public function getKey(): string
    {
        return 'summary';
    }

    public function getSortOrder(): int
    {
        return 5; // Render before the shipped 'identity' slice.
    }

    public function resolve(RuleInterface $rule, array $args): array
    {
        return [
            'human' => sprintf(
                '%s — %s%% off, %s active',
                $rule->getName(),
                (int) $rule->getDiscountAmount(),
                $rule->getIsActive() ? 'currently' : 'not currently'
            ),
        ];
    }
}
```

```xml
<!-- Vendor/Module/etc/di.xml -->
<type name="Magebit\McpMarketingTools\Tool\Marketing\CartRule\CartRuleGet">
    <arguments>
        <argument name="fieldResolvers" xsi:type="array">
            <item name="summary" xsi:type="object">
                Vendor\Module\Model\CartRuleSummaryResolver
            </item>
        </argument>
    </arguments>
</type>
```

The available field-resolver interfaces, by tool family:

- `Magebit\McpMarketingTools\Api\CatalogRuleFieldResolverInterface` — for
  `marketing.catalog_rule.list` / `.get`. `resolve()` receives a
  `Magento\CatalogRule\Model\Rule` (the model, not the API interface — the
  API interface is missing several fields catalog rules need).
- `Magebit\McpMarketingTools\Api\CartRuleFieldResolverInterface` — for
  `marketing.cart_rule.list` / `.get`. `resolve()` receives a
  `Magento\SalesRule\Api\Data\RuleInterface`.
- `Magebit\McpMarketingTools\Api\CouponFieldResolverInterface` — for
  `marketing.cart_rule.coupon.list` / `.get`. `resolve()` receives a
  `Magento\SalesRule\Api\Data\CouponInterface`.

Duplicate keys across registered resolvers fail loud at runtime via
`ResolverPipeline` — two modules cannot quietly fight over `summary`.

## Adding a filter translator

A filter translator handles one or more keys under a `list` tool's
`filters` argument. Built-in keys (handled inline by the search builders)
take priority; only unhandled keys fall through to translators.

### Example — filter `marketing.cart_rule.list` by `simple_action`

```php
// Vendor/Module/Model/CartRuleSimpleActionFilter.php
namespace Vendor\Module\Model;

use Magebit\McpMarketingTools\Api\CartRuleFilterTranslatorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CartRuleSimpleActionFilter implements CartRuleFilterTranslatorInterface
{
    public function supports(string $key): bool
    {
        return $key === 'simple_action';
    }

    public function translate(string $key, mixed $value, SearchCriteriaBuilder $builder): void
    {
        $builder->addFilter('simple_action', $value);
    }
}
```

```xml
<!-- Vendor/Module/etc/di.xml -->
<type name="Magebit\McpMarketingTools\Model\Search\CartRuleSearchBuilder">
    <arguments>
        <argument name="filterTranslators" xsi:type="array">
            <item name="simple_action" xsi:type="object">
                Vendor\Module\Model\CartRuleSimpleActionFilter
            </item>
        </argument>
    </arguments>
</type>
```

Translator interfaces, by tool family:

- `Magebit\McpMarketingTools\Api\CatalogRuleFilterTranslatorInterface` —
  receives a `Magento\CatalogRule\Model\ResourceModel\Rule\Collection`
  (catalog rules use a collection, not SearchCriteria).
- `Magebit\McpMarketingTools\Api\CartRuleFilterTranslatorInterface` —
  receives a `Magento\Framework\Api\SearchCriteriaBuilder`.
- `Magebit\McpMarketingTools\Api\CouponFilterTranslatorInterface` —
  receives a `Magento\Framework\Api\SearchCriteriaBuilder`.

Translators are consulted in DI order. The first translator whose
`supports()` returns `true` for a given key handles it; subsequent
translators do not see that key.

## ACL layering for custom write tools

If you ship a custom write tool, mirror the layering used by the shipped
writes:

1. Implement `Magebit\Mcp\Api\UnderlyingAclAwareInterface` and return the
   matching admin-UI ACL resource id from `getUnderlyingAclResource()`.
   For promotion-engine writes the right values are:
   - `Magento_CatalogRule::promo_catalog` (catalog rule writes)
   - `Magento_CatalogRule::promo_catalog_update_rules` (apply / reindex)
   - `Magento_SalesRule::quote` (cart rule + coupon writes)
2. Add an ACL resource under
   `Magento_Backend::admin → system → Magebit_Mcp::mcp → Magebit_Mcp::tools`,
   with id `<Vendor>_<Module>::tool_<dotted_tool_name_with_underscores>`.
3. Return `WriteMode::WRITE` from `getWriteMode()` and `true` from
   `getConfirmationRequired()` for any operation that mutates rules,
   coupons, or storefront pricing.

The `Magebit_Mcp` handler enforces both the MCP-specific ACL and the
underlying admin ACL, ensuring an MCP client cannot do anything an
admin-UI session of the same admin user could not.
