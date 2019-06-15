<?php namespace AlwaysBlank\Brief;

use AlwaysBlank\Brief\Exceptions\CannotSetProtectedKeyException;
use AlwaysBlank\Brief\Exceptions\WrongArgumentTypeException;

class Brief
{
    private $arguments = [];

    /**
     * A limited list of terms that cannot be used as argument keys.
     *
     * @var array|object
     */
    static $protected = ['protected', 'arguments'];

    public function __construct($items = [])
    {
        $this->store(self::normalizeInput($items));
    }

    /**
     * Attempt to convert input to a format Brief understands.
     *
     * @param $items
     *
     * @return object|array|Brief
     * @throws WrongArgumentTypeException
     */
    protected static function normalizeInput($items)
    {
        if (is_a($items, self::class)) {
            return $items;
        }

        if (is_object($items) && count(get_object_vars($items)) > 0) {
            return get_object_vars($items);
        }

        if ( ! is_array($items)) {
            throw new WrongArgumentTypeException("Did not pass array or iterable object.");
        }

        return $items;
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
     * @param iterable|object|Brief $items
     *
     * @return Brief
     * @throws CannotSetProtectedKeyException
     * @throws WrongArgumentTypeException
     */
    public static function make($items): Brief
    {
        $normalized = self::normalizeInput($items);
        if (empty($normalized)) {
            return new self([]);
        } elseif (is_a($normalized, self::class)) {
            return $normalized;
        } elseif (is_string(self::checkKeys($normalized))) {
            throw new CannotSetProtectedKeyException(
                sprintf("The key `%s` is prohibited.", self::checkKeys($normalized))
            );
        }

        return new self($normalized);
    }

    protected function storeSingle($value, string $key = null, int $order = null): self
    {
        if (false === $this::isKeyAllowed($key)) {
            throw new CannotSetProtectedKeyException(
                sprintf("The key `%s` is prohibited.", $key)
            );
        }

        /**
         * If no key is passed, use the order.
         * Otherwise, it will overwrite any other item(s) passed
         * without keys.
         */
        if (null === $key) {
            $key = $this->getIncrementedOrder();
        }

        $this->arguments[$key] = [
            'value' => $value,
            'order' => $order
        ];

        return $this;
    }

    protected function store(array $values, int $order_start = 0)
    {
        $i = $order_start;
        foreach ($values as $key => $value) {
            $this->storeSingle($value, $key, $i);
            $i++;
        }

        return $this;
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
            if (false === self::isKeyAllowed($key)) {
                return $key;
            }
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
        return ! in_array($key, self::$protected);
    }

    /**
     * True if key has been set; false otherwise.
     *
     * @param string $name
     *
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
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getArgument($name);
    }

    public function __set(string $name, $value)
    {
        try {
            $this->storeSingle($value, $name, $this->getIncrementedOrder());
        } catch (CannotSetProtectedKeyException $e) {
            echo "Could not set this value: " . $e->getMessage();
        }
    }

    /**
     * Gets a value if the key exists, returns bool `false` otherwise.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getArgument($name)
    {
        return isset($this->arguments[$name])
            ? $this->getValue($this->arguments[$name])
            : false;
    }

    protected function getValue($item)
    {
        return isset($item['value'])
            ? $item['value']
            : false;
    }

    protected function getArgumentsSortedByOrder()
    {
        $rekeyed = array_column($this->arguments, null, 'order');
        ksort($rekeyed);

        return $rekeyed;
    }

    protected function getOrderLimit(string $which = 'start', string $returnOnly = null)
    {
        $ordered = $this->getArgumentsSortedByOrder();
        if ('start' === $which) {
            $limit = reset($ordered);
        } else { // i.e. 'end'
            $limit = end($ordered);
        }

        if (null !== $returnOnly && isset($limit[$returnOnly])) {
            return $limit[$returnOnly];
        }

        return $limit;
    }

    protected function getHighestOrder()
    {
        return $this->getOrderLimit('end', 'order');
    }

    protected function getLowestOrder()
    {
        return $this->getOrderLimit('start', 'order');
    }

    protected function getIncrementedOrder(): int
    {
        return $this->getHighestOrder() + 1;
    }

    protected function getFilledOrdered($return = null)
    {
        $orders = $this->getArgumentsSortedByOrder();

        return array_map(function ($key) use ($orders, $return) {
            return $orders[$key] ?? ['order' => $key, 'value' => $return];
        }, range($this->getLowestOrder(), $this->getHighestOrder()));
    }

    public function getOrdered($return = null)
    {
        return array_column($this->getFilledOrdered($return), 'value', 'order');
    }

    /**
     * Pass an unmodified Brief to an callable.
     *
     * If the callable does not understand Briefs or how to get arguments from objects,
     * you should probably use `pass()` instead.
     *
     * @param callable $callable
     *
     * @return mixed
     */
    public function debrief(callable $callable)
    {
        return call_user_func($callable, $this);
    }

    /**
     * Pass the contents of a Brief as a series of arguments to callable.
     *
     * This method allows for Brief to easily interact with methods that do not know how to handle it
     * specifically.
     *
     * @param callable $callable
     *
     * @return mixed
     */
    public function pass(callable $callable)
    {
        return call_user_func_array($callable, $this->getOrdered());
    }
}
