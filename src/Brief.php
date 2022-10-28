<?php namespace AlwaysBlank\Brief;

class Brief
{
    /**
     * A limited list of terms that cannot be used as argument keys.
     *
     * @var array|object
     */
    static $protected = ['protected', 'store', 'aliases', 'logger', 'callables'];
    /**
     * An array of aliases for internal terms. The key is the alias; the value
     * is the internal key.
     *
     * @var array
     */
    protected $aliases = [];
    /**
     * Contains all data that the Brief stores.
     *
     * @var array
     */
    private $store = [];
    /**
     * This collects callables that the Brief needs, or might want,
     * access to internally. These are generally for internal functions,
     * *not* for things like `pass()` or `debrief()`.
     *
     * The key is the name by which the callable will be retrieved;
     * the value is the callable itself.
     *
     * Supports the following:
     *  - 'logger' -- For (optionally) logging internal errors.
     *
     * @var array
     */
    private $callables;

    /**
     * Brief constructor.
     *
     * @param iterable|object|Brief $items
     * @param array                 $settings
     */
    public function __construct($items = null, array $settings = [])
    {
        $this->callables = new Workers();

        if (is_a($items, self::class)) {
            $this->import($items, $settings);
        } else {
            $this->parseSettings($settings);
            $this->store($this->normalizeInput($items));
        }
    }

    /**
     * This imports the content of an existing Brief into this one.
     *
     * This is notably distinct from the behavior of passing a Brief to the
     * `make()` factory method, because that will return the *same* Brief it
     * was given, while this method will attempt to graft the content of the
     * Brief it receives onto a *new* Brief.
     *
     * @param Brief $items
     * @param       $settings
     */
    protected function import(Brief $items, $settings)
    {
        [
            'store'     => $store,
            'aliases'   => $aliases,
            'callables' => $callables
        ] = $items->export();
        $this->store     = $store;
        $this->aliases   = $aliases;
        $this->callables = $callables;
        if (is_array($settings) && count($settings) > 0) {
            $this->parseSettings($settings);
        }
    }

    /**
     * This returns the unmodified internal settings properties.
     *
     * This should rarely be used: It is mostly useful for making a copy of an
     * existing brief.
     *
     * @return array
     */
    public function export()
    {
        return [
            'store'     => $this->store,
            'aliases'   => $this->aliases,
            'callables' => $this->callables,
        ];
    }

    /**
     * Parse any aliases described in settings.
     *
     * @param array $aliases
     */
    public function parseAliasSetting($aliases)
    {
        if ( ! empty($aliases) && is_array($aliases)) {
            $compiled = [];
            foreach ($aliases as $key => $terms) {
                if (is_string($terms)) {
                    $terms = [$terms];
                }
                if ( ! is_array($terms)) {
                    continue;
                }
                $compiled = array_merge($compiled, array_fill_keys(array_values($terms), $key));
            }
            $this->aliases = array_merge($this->aliases, $compiled);
        }
    }

    /**
     * Parse settings into the Brief.
     *
     * This expects an array with particular keys; only keys that correspond to
     * expected settings will be parsed. Effectively this is whitelisting
     * appropriate keys.
     *
     * @param array $settings
     */
    public function parseSettings(array $settings)
    {
        if (empty($settings)) {
            return;
        }

        foreach ($settings as $key => $arg) {
            switch ($key) {
                case 'aliases':
                case 'alias':
                    $this->parseAliasSetting($arg);
                    break;
                case 'logger':
                    $this->setUpLogger($arg);
                    break;
                case 'isEmpty':
                    $this->setUpIsEmpty($arg);
                    break;
            }
        }
    }

    /**
     * Attach logger to Brief, if valid.
     *
     * The only argument here is a callable.
     * When called, it will receive the following arguments:
     *   - Event name
     *   - Event description
     *   - Clone of calling Brief at time of error
     *   - Array of any relevant data
     *
     * If set to `true`, then logs will simply be passed to `error_log()` and
     * therefore handled by the system's logging mechanisms.
     *
     * @param $callable
     */
    protected function setUpLogger($callable)
    {
        if (is_callable($callable)) {
            $this->callables->add('logger', $callable);
        } elseif (true === $callable) {
            // Use system error_log
            $this->callables->add('logger', function($name, $description, $clone, $data) {
                $message = join(' :: ', array_filter([$name, $description, var_export($data, true)]));
                error_log($message, 0);
            });
        }
    }

    /**
     * Attach isEmpty text to Brief, if valid.
     *
     * This callable will be passed a copy of the Brief to evaluate.
     *
     * @param $callable
     */
    protected function setUpIsEmpty($callable)
    {
        if (is_callable($callable)) {
            $this->callables->add('isEmpty', $callable);
        }
    }

    /**
     * Store multiple values, as defined by an array.
     *
     * Optionally, you can define the number from which Brief will start
     * ordering the items.
     *
     * @param array $values
     * @param int   $order_start
     *
     * @return $this
     */
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
     * Store a single value on the Brief.
     *
     * If you attempt to use a protected key (see self::$protected), the
     * data will not be stored. If logging is enabled, an error will be logged.
     *
     * @param mixed   $value
     * @param string  $key
     * @param integer $order
     *
     * @return self
     */
    protected function storeSingle($value, string $key = null, int $order = null): self
    {
        if (isset($this->aliases[$key])) {
            $key = $this->getAuthoritativeName($key) ?? $key;
        }

        if (false === $this::isKeyAllowed($key)) {
            $this->log('ProtectedKey', 'This key is protected and cannot be used.',
                ['key' => $key, 'protected_keys' => self::$protected]);

            return $this;
        }

        $this->store[$key] = [
            'value' => $value,
            'order' => $order
        ];

        return $this;
    }

    /**
     * This will get the authoritative name (either the key, or the key that
     * the passed alias points to).
     *
     * This returns null, *not* bool false, so that it will always cause
     * `isset()` to return bool false when used against an array (since an
     * array row can't have a key of `null`).
     *
     * @param $name
     *
     * @return string|null
     */
    protected function getAuthoritativeName($name)
    {
        if (isset($this->store[$name])) {
            return $name;
        }

        return $this->getAliasedKey($name) ?: null;
    }

    /**
     * Provide a public method to get authoritative keys.
     *
     * Keep in mind this returns *only the key*. In most cases you
     * will actually want the associated value, and should just get
     * that value by accessing a property normally.
     *
     * @param string $alias
     *
     * @return string|boolean
     */
    public function getAliasedKey(string $alias)
    {
        // Check alias for allowable keys
        if (false === $this::isKeyAllowed($alias)) {
            $this->log('ProtectedKey', 'This key is protected and cannot be used.',
                ['key' => $alias, 'protected_keys' => self::$protected]);

            return false;
        }

        $authoritative = $this->collapseAliasChain($alias);

        // Check resolved value for allowable keys
        if (false === $this::isKeyAllowed($authoritative)) {
            $this->log('ProtectedKey', 'This key is protected and cannot be used.',
                ['key' => $authoritative, 'protected_keys' => self::$protected]);

            return false;
        }

        return $authoritative;
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
     * Log an error to the defined logger, if any.
     *
     * @param string      $name
     * @param string|null $description
     * @param array       $data
     */
    public function log(string $name, string $description = null, array $data = [])
    {
        if ( ! $this->hasLogger()) {
            return;
        }

        $clone = clone $this;
        $this->callables->call('logger', $name, $description, $clone, $data);
    }

    /**
     * Whether this Brief has a valid logger.
     *
     * @return boolean
     */
    public function hasLogger()
    {
        return $this->callables->isCallable('logger');
    }

    /**
     * Attempt to collapse an alias chain and determine the authoritative key.
     *
     * A result of "false" means that this method has encountered an infitite loop,
     * or is otherwise unable to determine if an alias chain leads anywhere.
     * If you recieve a "false," assume that there is no corresponding key.
     *
     * @param string $alias
     * @param array  $chain
     *
     * @return string|boolean
     */
    protected function collapseAliasChain(string $alias, $chain = [])
    {
        if (count($chain) > count($this->aliases)) {
            // This seems like an infinite loop
            return false;
        }

        /**
         * This handles both the end of the chain *and* attempting to call
         * aliases that don't exist.
         */
        if ( ! isset($this->aliases[$alias])) {
            $final = array_pop($chain);
            if (is_string($final)) {
                /**
                 * This means we've reached the end of a valid chain, and can return
                 * a final value.
                 */
                return $final;
            }

            /**
             * This means that either this was called on an alias that does not
             * exist, so on the first loop the chain is empty and the alias is
             * unset, or (much less likely) the alias points to a non-string value.
             */
            return false;
        }

        $chain[] = $this->aliases[$alias];

        return $this->collapseAliasChain($this->aliases[$alias], $chain);
    }

    /**
     * Attempt to convert input to a format Brief understands.
     *
     * @param $items
     *
     * @return object|array|Brief
     */
    protected function normalizeInput($items)
    {
        if (is_object($items) && count(get_object_vars($items)) > 0) {
            return get_object_vars($items);
        }

        if (null === $items || is_bool($items)) {
            return [];
        }

        if ( ! is_array($items)) {
            $this->log("WrongArgumentType", "Did not pass array or iterable object.", ['items' => $items]);

            return [];
        }

        return $items;
    }

    /**
     * Creates an empty Brief.
     *
     * @param array $settings
     *
     * @return EmptyBrief
     */
    public static function empty(array $settings = [])
    {
        return new EmptyBrief($settings);
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
     * @param array                 $settings
     *
     * @return Brief
     */
    public static function make($items = null, array $settings = []): Brief
    {
        if (is_a($items, self::class)) {
            return $items;
        }

        return new self($items, $settings);
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
        return $this->__get($name) ?? false;
    }

    /**
     * Get the value of a key if it is set; return null otherwise.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->maybeGetByOrder($name) ?: $this->get($name);
    }

    /**
     * Allows you to set a value dynamically: `$Brief->newValue = 'something';`.
     *
     * This respects aliases; data passed to an alias will be stored under the
     * authoritative key for that alias. This means that you cannot create a "new"
     * key that has the name of an alias.
     *
     * @param string $name
     * @param        $value
     */
    public function __set(string $name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * TODO: Make a `maybeSetByOrder` that allows us to try and set an ordered
     * item by numeric key using an underscore prop. Should first text to see if
     * there is an existing prop using the underscore string.
     *
     * @param $key
     *
     * @return bool|mixed
     */

    protected function maybeGetByOrder($key)
    {
        /**
         * Integers are not allowed as object properties, so here we make a
         * a way to easily access ordered data; by prefixing the order number
         * with an underscore, i.e. `$brief->_1`.
         */
        $order = $this->getIntFromUnderscoreProp($key);
        if (null !== $order) {
            $value = $this->getByOrder($order);
            if (null !== $value) {
                return $value;
            }
        }

        return false;
    }

    protected function getIntFromUnderscoreProp($key)
    {
        if (0 === strpos($key, '_') && is_numeric(substr($key, 1))) {
            return intval(substr($key, 1));
        }

        return null;
    }

    /**
     * Get a value based on its order.
     *
     * @param integer $int
     *
     * @return mixed
     */
    public function getByOrder(int $int)
    {
        $ordered = $this->getOrdered();

        return $ordered[$int] ?? null;
    }

    /**
     * Get all data on the Brief, in order, with missing keys filled.
     *
     * Second argument is the value that missing keys will be filled with.
     *
     * @param null|mixed $fill
     *
     * @return array
     */
    public function getOrdered($fill = null)
    {
        return array_column($this->getFilledOrdered($fill), 'value', 'order');
    }

    /**
     * Fill in any missing numeric keys when ordered by number.
     *
     * Second argument is the value that missing keys will be filled with.
     *
     * @param null|mixed $fill
     *
     * @return array
     */
    protected function getFilledOrdered($fill = null)
    {
        $orders = $this->getDataSortedByOrder();

        return array_map(function ($key) use ($orders, $fill) {
            return $orders[$key] ?? ['order' => $key, 'value' => $fill];
        }, range($this->getLowestOrder(), $this->getHighestOrder()));
    }

    /**
     * Get all data stored on the Brief, in the correct order.
     *
     * @return array
     */
    protected function getDataSortedByOrder()
    {
        $rekeyed = array_column($this->store, null, 'order');
        ksort($rekeyed);

        return $rekeyed;
    }

    /**
     * Return the lowest number in the internal order.
     *
     * @return integer
     */
    protected function getLowestOrder()
    {
        return $this->getOrderLimit('start', 'order');
    }

    /**
     * Find the data at the beginning or end of the internal order.
     *
     * The second parameters allows you to specify what internal data attribute
     * you want (i.e. 'value', 'order').
     *
     * @param string $which
     * @param string $attribute
     *
     * @return mixed
     */
    protected function getOrderLimit(string $which = 'start', string $attribute = null)
    {
        $ordered = $this->getDataSortedByOrder();
        if ('start' === $which) {
            $limit = reset($ordered);
        } else { // i.e. 'end'
            $limit = end($ordered);
        }

        if (null !== $attribute && isset($limit[$attribute])) {
            return $limit[$attribute];
        }

        return $limit;
    }

    /**
     * Return the largest number in the internal order.
     *
     * @return integer
     */
    protected function getHighestOrder()
    {
        return $this->getOrderLimit('end', 'order');
    }

    /**
     * Get a value from the Brief.
     *
     * If $key is an integer, it will get the data base on order, on
     * the assumption that this is a numeric array. If $key is a string,
     * they it will get the data based on the key.
     *
     * @param string|int $key
     *
     * @return mixed|null
     */
    public function get($key)
    {
        $internalKey = $this->resolveInternalKey($key);
        if ($internalKey === null) {
            return null;
        }

        return is_int($internalKey)
            ? $this->getByOrder($internalKey)
            : $this->getByKey($internalKey);
    }

    /**
     * Get the value of an internal storage element.
     *
     * Returns null if the item has no value.
     *
     * @param array $item
     *
     * @return mixed|null
     */
    protected function getValue($item)
    {
        return isset($item['value'])
            ? $item['value']
            : null;
    }

    /**
     * Gets a value if the key exists, returns null otherwise.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getByKey($name)
    {
        return isset($this->store[$name])
            ? $this->getValue($this->store[$name])
            : null;
    }

    /**
     * Get the next number in the order.
     *
     * @return integer
     */
    protected function getIncrementedOrder(): int
    {
        return $this->getHighestOrder() + 1;
    }

    /**
     * Store a value.
     *
     * If $key is an integer, then the value will be stored with that as
     * the order and the key name. If $key is a string, then the value will
     * be stored under that key, and its order will be the next in order.
     *
     * @param string|int $key
     * @param mixed      $value
     */
    public function set($key, $value)
    {
        if (is_int($key)) {
            $this->storeSingle($value, (string)$key, $key);
        } elseif (is_string($key)) {
            $authoritativeName = $this->getAuthoritativeName($key);
            $this->storeSingle(
                $value,
                $authoritativeName ?? $key,
                $this->getIncrementedOrder()
            );
        }
    }

    /**
     * Pass an unmodified Brief to an callable.
     *
     * If the callable does not understand Briefs or how to get properties from
     * objects, you should probably use `pass()` instead.
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
     * Pass the contents of a Brief as a series of arguments to a callable.
     *
     * This method allows for Brief to easily interact with methods that do not
     * know how to handle it specifically.
     *
     * @param callable $callable
     *
     * @return mixed
     */
    public function pass(callable $callable)
    {
        return call_user_func_array($callable, $this->getOrdered());
    }

    /**
     * Very similar to Brief::pass(), but passes an array instead of a series
     * of arguments.
     *
     * Passes a keyed array by default, but passing `false` to the second
     * argument will pass an ordered numeric array instead.
     *
     * @param callable $callable
     *
     * @param bool     $keyed
     *
     * @return mixed
     */
    public function passArray(callable $callable, $keyed = true)
    {
        $arg = $keyed
            ? $this->getKeyed()
            : $this->getOrdered();

        return call_user_func($callable, $arg);
    }

    /**
     * Get all data in this Brief as a keyed array.
     *
     * @return array
     */
    public function getKeyed()
    {
        return array_map(function ($block) {
            return $block['value'];
        }, $this->store);
    }

    /**
     * Pass an array of keys, and return the value for the first one that matches.
     *
     * @param array|string $keys
     *
     * @return bool
     */
    public function find($keys)
    {
        // Be friendly
        if (is_string($keys)) {
            return $this->getByKey($keys);
        }

        // ...Otherwise, it has to be an array
        if ( ! is_array($keys)) {
            return null;
        }

        // Prevent infinite recursion
        if (empty($keys)) {
            return null;
        }

        $get = array_shift($keys);

        return $this->getByKey($get) ?: $this->find($keys);
    }

    /**
     * Call a callable on each item of a copy of this Brief.
     *
     * This is method is intended for use with keyed data. Ordered
     * data may produce strange results.
     *
     * This acts on a copy of the Brief on which it is called, and returns the
     * new Brief, leaving the original unmodified. If you don't want this
     * behavior, use transform().
     *
     * @param callable $callable
     *
     * @return Brief
     */
    public function map(callable $callable)
    {
        $New = clone $this;

        return $New->transform($callable);
    }

    /**
     * Call a callable on each item of this Brief.
     *
     * This is method is intended for use with keyed data. Ordered
     * data may produce strange results.
     *
     * This acts directly on the Brief on which it is called, and returns that
     * Brief. Be careful; this means that your original Brief is changed. If
     * you want a *copy* of your Brief, use map().
     *
     * @param callable $callable
     *
     * @return Brief
     */
    public function transform(callable $callable)
    {
        foreach ($this->getKeyed() as $key => $value) {
            $callable($value, $key, $this);
        }

        return $this;
    }

    /**
     * Determines whether the Brief is empty.
     *
     * The default test is somewhat naive, but you can pass your own test via
     * the `isEmpty` setting on instantiation.
     *
     * @return bool|mixed
     */
    public function isEmpty()
    {
        $Clone = clone $this;

        if ($this->callables->isCallable('isEmpty')) {
            return $this->callables->call('isEmpty', $Clone);
        }

        return count(array_filter(array_column($this->store, 'value'), function ($value) {
                return $value !== null;
            })) < 1;
    }

    /**
     * If the Brief is *not* empty.
     *
     * @return bool
     */
    public function isNotEmpty()
    {
        return !$this->isEmpty();
    }

    /**
     * This returns the *internal* key used to store this data.
     *
     * @param string|int $key
     *
     * @return string|int|null
     */
    protected function resolveInternalKey($key) {
        if (is_int($key)) {
            $ordered = $this->getOrdered();
            return isset($ordered[$key]) ? $key : null;
        } elseif (is_string($key)) {
            return $this->getAuthoritativeName($key);
        }

        return null;
    }
}
