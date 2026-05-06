<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpMarketingTools\Model\Search;

use DateTime;
use DateTimeInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Shared input coercers for the marketing search builders.
 */
class FilterValueCoercer
{
    /**
     * @param mixed $value
     * @return int
     * @throws LocalizedException
     */
    public function boolToInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value;
        }
        if (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'], true)) {
            return in_array(strtolower($value), ['true', '1'], true) ? 1 : 0;
        }
        throw new LocalizedException(__(
            'Boolean filter expected, got "%1".',
            is_scalar($value) ? (string) $value : gettype($value)
        ));
    }

    /**
     * @param mixed $value
     * @param string $key
     * @return string
     * @throws LocalizedException
     */
    public function coerceIsoDate(mixed $value, string $key): string
    {
        if (!is_string($value) || $value === '') {
            throw new LocalizedException(__('"%1" must be an ISO date string.', $key));
        }
        if (
            DateTime::createFromFormat(DateTimeInterface::ATOM, $value) === false
            && DateTime::createFromFormat('Y-m-d\TH:i:s', $value) === false
            && DateTime::createFromFormat('Y-m-d H:i:s', $value) === false
            && DateTime::createFromFormat('Y-m-d', $value) === false
        ) {
            throw new LocalizedException(__(
                '"%1" must be an ISO 8601 date or datetime string.',
                $key
            ));
        }
        return $value;
    }

    /**
     * Escape `\`, `%`, `_` before splicing user input into a LIKE pattern.
     *
     * @param string $value
     * @return string
     */
    public function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param mixed $value
     * @param string $key
     * @return array<int, int>
     * @throws LocalizedException
     */
    public function coerceIntList(mixed $value, string $key): array
    {
        $list = is_array($value) ? $value : [$value];
        $out = [];
        foreach ($list as $v) {
            if (!is_numeric($v) || (int) $v < 0) {
                throw new LocalizedException(__('"%1" values must be non-negative integers.', $key));
            }
            $out[] = (int) $v;
        }
        return $out;
    }
}
