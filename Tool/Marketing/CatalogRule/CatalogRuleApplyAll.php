<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Tool\Marketing\CatalogRule;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\CatalogRule\Model\Rule\Job;
use Magento\Framework\Exception\LocalizedException;

/**
 * Invalidates `catalogrule_rule` so the next reindex pass rebuilds the
 * `catalogrule_product_*` aggregation tables. On `realtime` indexers the
 * rebuild fires immediately and can take minutes — run off-peak.
 */
class CatalogRuleApplyAll implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'marketing.catalog_rule.apply_all';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_catalog_rule_apply_all';

    /**
     * @param Job $job
     */
    public function __construct(
        private readonly Job $job
    ) {
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    /** @inheritDoc */
    public function getTitle(): string
    {
        return 'Apply All Catalog Rules';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Invalidate the catalogrule_rule indexer so the next reindex '
            . 'pass walks every active rule and rebuilds the '
            . 'catalogrule_product_* aggregation tables that drive storefront '
            . 'prices. On indexers in realtime mode the rebuild fires '
            . 'immediately and can take minutes on large catalogs — run '
            . 'off-peak. On schedule mode it runs on the next cron pass. '
            . 'Mirrors admin *Marketing → Catalog Price Rule → Apply Rules*.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()->toArray();
    }

    /** @inheritDoc */
    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /** @inheritDoc */
    public function getUnderlyingAclResource(): ?string
    {
        return 'Magento_CatalogRule::promo_catalog';
    }

    /** @inheritDoc */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::WRITE;
    }

    /** @inheritDoc */
    public function getConfirmationRequired(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function execute(array $arguments): ToolResultInterface
    {
        unset($arguments);

        $this->job->applyAll();

        $error = $this->job->hasError() ? (string) $this->job->getError() : null;

        $payload = [
            'applied' => $error === null,
            'error' => $error,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode apply_all result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            isError: $error !== null,
            auditSummary: [
                'applied' => $error === null,
                'has_error' => $error !== null,
            ]
        );
    }
}
