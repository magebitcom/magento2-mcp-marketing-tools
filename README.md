# Magento2 MCP - Marketing Tools

This is a sub-module for the [Magento2 MCP module](https://github.com/magebitcom/magento2-mcp-module)

----

Promotion-domain MCP tools for `Magebit_Mcp`. Reads and toggles **catalog
price rules** and **cart price rules**, and manages **coupon supply** for
cart rules — all as MCP tools.

Each tool is a thin wrapper over a Magento service contract
(`CatalogRuleRepositoryInterface`, `SalesRule\RuleRepositoryInterface`,
`CouponRepositoryInterface`, `CouponManagementInterface`) and composes its
read responses from field resolvers that 3rd-party modules can extend.

## Install

```bash
composer require magebitcom/magento2-mcp-marketing-tools
bin/magento module:enable Magebit_McpMarketingTools
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Tool catalog

### Catalog rules (read)

| Tool | What it does |
|---|---|
| `marketing.catalog_rule.list` | Paginated catalog-rule search; filter by name, is_active, customer_group_id, website_id, from_date_after, to_date_before. |
| `marketing.catalog_rule.get` | Single catalog rule by id with full detail (identity, scope, schedule, conditions, action, lifecycle). |

### Cart rules (read)

| Tool | What it does |
|---|---|
| `marketing.cart_rule.list` | Paginated cart-rule search; filter by name, is_active, coupon_type, customer_group_id, website_id, date range. |
| `marketing.cart_rule.get` | Single cart rule by id including the full `condition` and `action_condition` trees as JSON. |

### Coupons (read)

| Tool | What it does |
|---|---|
| `marketing.cart_rule.coupon.list` | Paginated coupon search; filter by rule_id, code substring, type (MANUAL/GENERATED), is_primary, created_after. |
| `marketing.cart_rule.coupon.get` | Single coupon by id. |

### Catalog rules (write)

Write tools require the global `magebit_mcp/general/allow_writes` flag **and**
the token's own `allow_writes` flag to be `1`. Destructive operations
additionally set the `requires_confirmation` hint so MCP clients prompt
before firing.

| Tool | Confirm? | What it does |
|---|---|---|
| `marketing.catalog_rule.set_active` | no | Toggles `is_active` on the rule; storefront prices update on the next `catalogrule_rule` reindex. |
| `marketing.catalog_rule.delete` | yes | Permanently deletes a catalog rule. |
| `marketing.catalog_rule.apply_all` | yes | Invalidates `catalogrule_rule` so the next reindex pass rebuilds the `catalogrule_product_*` aggregation tables. **Heavy** on `realtime` indexers — run off-peak. |

### Cart rules (write)

| Tool | Confirm? | What it does |
|---|---|---|
| `marketing.cart_rule.set_active` | no | Toggles `is_active` on the rule. Disabling immediately stops it applying to new carts; existing orders are unaffected. |
| `marketing.cart_rule.delete` | yes | Permanently deletes a cart rule and cascade-deletes its coupons via FK. Existing orders are unaffected. |

### Coupons (write)

| Tool | Confirm? | What it does |
|---|---|---|
| `marketing.cart_rule.coupon.generate` | yes | Bulk-generates coupon codes for a cart rule. The rule must have `coupon_type = SPECIFIC_COUPON` and `use_auto_generation = true`. |
| `marketing.cart_rule.coupon.delete` | yes | Bulk-deletes coupons by id list or code list. Existing orders that previously used a deleted code are unaffected. |

Every write tool also implements `Magebit\Mcp\Api\UnderlyingAclAwareInterface`
so the handler blocks calls from admins who wouldn't be allowed to perform
the same action in the admin UI.

## Extending

See `docs/EXTENDING.md` for:
- adding a new field to any tool response via `*FieldResolverInterface`;
- adding a new filter to a `list` tool via `*FilterTranslatorInterface`;
- the ACL layering rules for custom write tools.

## License

Released under the [MIT License](LICENSE).

---

![magebit (1)](https://github.com/user-attachments/assets/cdc904ce-e839-40a0-a86f-792f7ab7961f)

*Have questions or need help? Contact us at info@magebit.com*
