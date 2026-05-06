<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\FieldResolver\CatalogRule;

use Magebit\McpMarketingTools\Api\CatalogRuleFieldResolverInterface;
use Magento\CatalogRule\Model\Rule;

/**
 * Schedule slice — from_date, to_date.
 */
class ScheduleResolver implements CatalogRuleFieldResolverInterface
{
    public const KEY = 'schedule';

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Rule $rule, array $args): array
    {
        unset($args);
        // The Model\Rule docblock types from_date / to_date as plain string,
        // but unset rules return empty string — emit null in JSON for clarity.
        $from = (string) $rule->getFromDate();
        $to = (string) $rule->getToDate();
        return [
            'from_date' => $from === '' ? null : $from,
            'to_date' => $to === '' ? null : $to,
        ];
    }
}
