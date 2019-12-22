<?php namespace AlwaysBlank\Brief;

class Brief
{
    /**
     * Contains all data that the Brief stores.
     *
     * @var array
     */
    private $store = [];

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
     * @var callables
     */
    private $callables = [];

    /**
     * Brief constructor.
     *
     * @param iterable|object|Brief $items
     * @param array                 $settings
     */
    public function __construct($items = null, array $settings = [])
    {
        if (is_a($items, self::class)) {
            $this->import($items, $settings);
        } else {
            $this->parseSettings($settings);
            $this->store($this->normalizeInput($items));
        }
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
            }
        }
    }

    /**
     * Parse any aliases described in settings.
     *
     * @param array $aliases
     */
    public function parseAliasSetting($aliases)
    {
        if (!empty($aliases) && is_array($aliases)) {
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
     * Attempt to collapse an alias chain and determine the authoritative key.
     * 
     * A result of "false" means that this method has encountered an infitite loop,
     * or is otherwise unable to determine if an alias chain leads anywhere.
     * If you recieve a "false," assume that there is no corresponding key.
     *
     * @param string $alias
     * @param array $chain
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
     * Provide a public method to get authoritative keys.
     * 
     * Keep in mind this returns *only the key*. In most cases you
     * will actually want the associated value, and should just get
     * that value by accessing a property normally.
     *
     * @param string $alias
     * @return string|boolean
     */
    public function getAliasedKey(string $alias)
    {
        return $this->collapseAliasChain($alias);
    }

    /**
     * Store a single value on the Brief.
     * 
     * If you attempt to use a protected key (see self::$protected), the
     * data will not be stored. If logging is enabled, an error will be logged.
     *
     * @param mixed $value
     * @param string $key
     * @param integer $order
     * @return self
     */
    protected function storeSingle($value, string $key = null, int $order = null): self
    {
        if (false === $this::isKeyAllowed($key)) {
            $this->log('ProtectedKey', 'This key is protected and cannot be used.',
                ['key' => $key, 'protected_keys' => self::$protected]);

            return $this;
        }

        if (isset($this->aliases[$key])) {
            $key = $this->getAuthoritativeName($key) ?? $key;
        }

        $this->store[$key] = [
            'value' => $value,
            'order' => $order
        ];

        return $this;
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
        return in_array($name, array_keys($this->store));
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
        return $this->getArgument($this->getAuthoritativeName($name));
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
        $this->storeSingle($value, $this->getAuthoritativeName($name) ?? $name, $this->getIncrementedOrder());
    }

    /**
     * Gets a value if the key exists, returns null otherwise.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getArgument($name)
    {
        return isset($this->store[$name])
            ? $this->getValue($this->store[$name])
            : null;
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
     * Get the value of an internal storage element.
     * 
     * Returns null if the item has no value.
     *
     * @param array $item
     * @return mixed|null
     */
    protected function getValue($item)
    {
        return isset($item['value'])
            ? $item['value']
            : null;
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
     * Find the data at the beginning or end of the internal order.
     * 
     * The second parameters allows you to specify what internal data attribute
     * you want (i.e. 'value', 'order').
     *
     * @param string $which
     * @param string $attribute
     * @return void
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
     * Return the lowest number in the internal order.
     *
     * @return integer
     */
    protected function getLowestOrder()
    {
        return $this->getOrderLimit('start', 'order');
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
     * Fill in any missing numeric keys when ordered by number.
     * 
     * Second argument is the value that missing keys will be filled with.
     *
     * @param mixed $return
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
     * Get all data on the Brief, in order, with missing keys filled.
     * 
     * Second argument is the value that missing keys will be filled with.
     *
     * @param mixed $return
     * @return array
     */
    public function getOrdered($fill = null)
    {
        return array_column($this->getFilledOrdered($fill), 'value', 'order');
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
     * Pass an array of keys, and return the value for the first one that matches.
     *
     * @param array $keys
     *
     * @return bool
     */
    public function find($keys)
    {
        // Be friendly
        if (is_string($keys)) {
            return $this->getArgument($keys);
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

        return $this->getArgument($get) ?: $this->find($keys);
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
            $this->callables['logger'] = $callable;
        } elseif (true === $callable) {
            $this->callables['logger'] = true; // Use system `error_log()`
        }
    }

    /**
     * Whether this Brief has a valid logger.
     *
     * @return boolean
     */
    public function hasLogger()
    {
        if (isset($this->callables['logger'])) {
            return true === $this->callables['logger'] || is_callable($this->callables['logger']);
        }

        return false;
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

        if (is_callable($this->callables['logger'])) {
            $clone = clone $this;
            call_user_func($this->callables['logger'], $name, $description, $clone, $data);
        } elseif (true === $this->callables['logger']) {
            $message = join(' :: ', array_filter([$name, $description, var_export($data, true)]));
            error_log($message, 0);
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
            'store' => $this->store,
            'aliases'   => $this->aliases,
            'callables' => $this->callables,
        ];
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
            'store' => $store,
            'aliases'   => $aliases,
            'callables' => $callables
        ] = $items->export();
        $this->store = $store;
        $this->aliases   = $aliases;
        $this->callables = $callables;
        if (is_array($settings) && count($settings) > 0) {
            $this->parseSettings($settings);
        }
    }
}
