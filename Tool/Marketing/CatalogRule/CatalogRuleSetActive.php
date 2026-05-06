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
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpMarketingTools\Model\EntityFinder;
use Magento\CatalogRule\Api\CatalogRuleRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Idempotent toggle of the catalog rule's `is_active` flag. No confirmation.
 * Saving invalidates `catalogrule_rule`; storefront prices update on the
 * next cron tick or via `marketing.catalog_rule.apply_all`.
 */
class CatalogRuleSetActive implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'marketing.catalog_rule.set_active';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_catalog_rule_set_active';

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
        return 'Activate / Deactivate Catalog Rule';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Set a catalog rule\'s `is_active` flag. Saving the rule '
            . 'invalidates the catalogrule_rule indexer; storefront prices '
            . 'update on the next cron tick or via '
            . '`marketing.catalog_rule.apply_all`.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('rule_id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->required()
                ->description('Numeric catalogrule.rule_id.'))
            ->boolean('is_active', fn (BooleanBuilder $b) => $b
                ->required()
                ->description('Target state: true to enable, false to disable.'))
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
        return false;
    }

    /** @inheritDoc */
    public function execute(array $arguments): ToolResultInterface
    {
        if (!array_key_exists('is_active', $arguments) || !is_bool($arguments['is_active'])) {
            throw new LocalizedException(__('"is_active" is required and must be a boolean.'));
        }
        $rule = $this->entityFinder->catalogRuleFrom($arguments);
        $rule->setIsActive($arguments['is_active'] ? 1 : 0);
        $saved = $this->catalogRuleRepository->save($rule);

        $payload = [
            'rule_id' => (int) $saved->getRuleId(),
            'is_active' => (bool) $saved->getIsActive(),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode set_active result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: $payload
        );
    }
}
