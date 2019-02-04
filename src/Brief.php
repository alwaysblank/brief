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
            throw new CannotSetProtectedKeyException(
                sprintf("The key `%s` is prohibited.", self::checkKeys($items))
            );
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

    /**
     * True if key has been set; false otherwise.
     *
     * @param string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return in_array($name, $this->arguments->keys());
    }

    /**
     * Get the value of a key if it is set; return false otherwise.
     * 
     * In the case where a key has the value of bool `false`, this will always return
     * the same value, as the "true" value is `false`. In this case, if you wanted to
     * be certain you were retrieving an actual value, you would need to do something
     * like this:
     * ```
     * return isset($Brief->valueSetToFalse) ? $Brief->valueSetToFalse : 'value is not set';
     * ```
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getArgument($name);
    }

    /**
     * Gets a value if the key exists, returns bool `false` otherwise.
     * 
     * This is just a wrapper for the Collection get() method, requests for values
     * should always be passed though it, for future architecture purposes.
     *
     * @param string $name
     * @return mixed
     */
    protected function getArgument($name)
    {
        return $this->arguments->get($name, false);
    }

    public function call(callable $callable)
    {
        return call_user_func($callable, $this);
    }

    public function callUnpacked(callable $callable)
    {
        return call_user_func_array($callable, $this->arguments->all());
    }
}
