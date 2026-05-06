<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Tool\Marketing\CartRule;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpMarketingTools\Model\EntityFinder;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\RuleRepositoryInterface;

/**
 * Cascade-deletes the cart rule + its coupons via FK; orders are unaffected.
 */
class CartRuleDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'marketing.cart_rule.delete';
    public const ACL_RESOURCE = 'Magebit_McpMarketingTools::tool_marketing_cart_rule_delete';

    /**
     * @param EntityFinder $entityFinder
     * @param RuleRepositoryInterface $cartRuleRepository
     */
    public function __construct(
        private readonly EntityFinder $entityFinder,
        private readonly RuleRepositoryInterface $cartRuleRepository
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
        return 'Delete Cart Price Rule';
    }

    /** @inheritDoc */
    public function getDescription(): string
    {
        return 'Permanently delete a cart price rule and cascade-delete its '
            . 'coupon rows. Existing orders that previously used a coupon '
            . 'under this rule are unaffected.';
    }

    /** @inheritDoc */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('rule_id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->required()
                ->description('Numeric salesrule.rule_id to delete.'))
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
        return 'Magento_SalesRule::quote';
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
        $rule = $this->entityFinder->cartRuleFrom($arguments);
        $ruleId = (int) $rule->getRuleId();
        $name = (string) $rule->getName();

        $deleted = $this->cartRuleRepository->deleteById($ruleId);

        $payload = [
            'rule_id' => $ruleId,
            'deleted' => $deleted,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
