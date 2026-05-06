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
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpMarketingTools\Model\EntityFinder;
use Magento\CatalogRule\Api\CatalogRuleRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class CatalogRuleDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'marketing.catalog_rule.delete';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_catalog_rule_delete';

    /**
     * @param EntityFinder $entityFinder
     * @param CatalogRuleRepositoryInterface $catalogRuleRepository
     */
    public function __construct(
        private readonly EntityFinder $entityFinder,
        private readonly CatalogRuleRepositoryInterface $catalogRuleRepository
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
        return 'Delete Catalog Rule';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Permanently delete a catalog price rule. Storefront prices '
            . 'are recomputed when the catalogrule_rule indexer next runs '
            . '(or via `marketing.catalog_rule.apply_all`).';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('rule_id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->required()
                ->description('Numeric catalogrule.rule_id to delete.'))
            ->toArray();
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
        $rule = $this->entityFinder->catalogRuleFrom($arguments);
        $ruleId = (int) $rule->getRuleId();
        $name = (string) $rule->getName();

        $deleted = $this->catalogRuleRepository->delete($rule);

        $json = json_encode([
            'rule_id' => $ruleId,
            'deleted' => $deleted,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode delete result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'rule_id' => $ruleId,
                'name' => $name,
                'deleted' => $deleted,
            ]
        );
    }
}
