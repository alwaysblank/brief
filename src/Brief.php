<?php

namespace AlwaysBlank\Brief;

use AlwaysBlank\Brief\Exceptions\CannotSetProtectedKeyException;
use Tightenco\Collect\Support\Collection;

class Brief
{
    private $arguments;

    /**
     * A limited list of terms that cannot be used as argument keys.
     *
     * @var array
     */
    static $protected = ['protected', 'arguments'];

    protected function __construct($items = [])
    {
        $this->arguments = new Collection($items);
    }

    /**
     * Create and receive a Brief.
     *
     * The usual usage is to pass a keyed array, which is then converted into a
     * Brief. If the argument passed is a Brief itself, this method simply returns
     * the original argument: The intent being to render this method idempotent
     * (so you can pass stuff to it without worrying about getting back
     * "nested" Briefs).
     *
     * @param iterable|Brief $items
     *
     * @return Brief
     * @throws CannotSetProtectedKeyException
     */
    public static function make($items) : Brief
    {
        if (empty($items)) {
            return new self([]);
        } elseif (is_a($items, self::class)) {
            return $items;
        } elseif (is_string(self::checkKeys($items))) {
            throw new CannotSetProtectedKeyException(sprintf("The key `%s` is prohibited.", self::checkKeys($items)));
        }

        return new self($items);
    }

    /**
     * Checks array keys to make sure there are not forbidden keys.
     *
     * @param array $items
     *
     * @return bool|string
     */
    public static function checkKeys(iterable $items)
    {
        foreach (array_keys($items) as $key) {
            if (false === self::isKeyAllowed($key)) return $key;
        }

        return true;
    }

    /**
     * Checks an individual key to see if it is allowed.
     *
     * @param int|string $key
     *
     * @return bool
     */
    public static function isKeyAllowed($key)
    {
        return !in_array($key, self::$protected);
    }
}
