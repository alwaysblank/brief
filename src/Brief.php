<?php namespace AlwaysBlank\Brief;

class Brief
{
    private $arguments = [];

    /**
     * A limited list of terms that cannot be used as argument keys.
     *
     * @var array|object
     */
    static $protected = ['protected', 'arguments', 'aliases', 'logger'];

    /**
     * An array of aliases for internal terms. The key is the alias; the value is the internal key.
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * If set, this provides a callable that can be used to log errors.
     * When called, it will receive the following arguments:
     *   - Event name
     *   - Event description
     *   - Clone of calling Brief at time of error
     *   - Array of any relevant data
     *
     * If set to `true`, then logs will simply be passed to `error_log()` and
     * therefore handled by the system's logging mechanisms.
     *
     * By default the value is null, and nothing will be logged.
     *
     * @var callable
     */
    private $callables = [];

    /**
     * Brief constructor.
     *
     * @param array|Brief $items
     * @param array       $settings
     */
    public function __construct($items = [], array $settings = [])
    {
        if (is_a($items, self::class)) {
            $this->import($items, $settings);
        } else {
            $this->parseSettings($settings);
            $this->store($this->normalizeInput($items));
        }
    }

    /**
     * Creates an empty Brief, CANNOT throw WrongArgumentException.
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
    public static function make($items, array $settings = []): Brief
    {
        if (is_a($items, self::class)) {
            return $items;
        }

        return new self($items, $settings);
    }

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

    public function parseAliasSetting($aliases)
    {
        if (empty($aliases) || ! is_array($aliases)) {
            return;
        }
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

    public function getAliasedKey(string $alias)
    {
        return $this->collapseAliasChain($alias);
    }

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

        $this->arguments[$key] = [
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
        return in_array($name, array_keys($this->arguments));
    }

    /**
     * Get the value of a key if it is set; return null otherwise.
     *
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
     * This respects aliases, so if you set something making it impossible to create a new key with the name of an
     * alias you have for another key.
     *
     * @param string $name
     * @param        $value
     */
    public function __set(string $name, $value)
    {
        $this->storeSingle($value, $this->getAuthoritativeName($name) ?? $name, $this->getIncrementedOrder());
    }

    /**
     * Gets a value if the key exists, returns `null` otherwise.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getArgument($name)
    {
        return isset($this->arguments[$name])
            ? $this->getValue($this->arguments[$name])
            : null;
    }

    /**
     * This will get the authoritative name (either the key, or the key that the passed alias points to).
     *
     * This returns null, *not* bool false, so that it will always cause `isset()` to return bool false when used
     * against an array (since an array row can't have a key of `null`).
     *
     * @param $name
     *
     * @return string|null
     */
    protected function getAuthoritativeName($name)
    {
        if (isset($this->arguments[$name])) {
            return $name;
        }

        return $this->getAliasedKey($name) ?: null;
    }

    protected function getValue($item)
    {
        return isset($item['value'])
            ? $item['value']
            : null;
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
     * Get all arguments in this Brief as a keyed array.
     *
     * @return array
     */
    public function getKeyed()
    {
        return array_map(function ($block) {
            return $block['value'];
        }, $this->arguments);
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

    /**__
     * Very similar to Brief::pass(), but passes an array instead of a series of arguments.
     *
     * Passes a keyed array by default, but passing `false` to the second argument will pass
     * an ordered numeric array instead.
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
     * This is method is intended for use with keyed arguments. Ordered
     * arguments may produce strange results.
     *
     * This acts directly on the Brief on which it is called, and returns that
     * Brief. Be careful; this means that your original Brief is changed. If you
     * want a copy of your Brief, use map().
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
     * This is method is intended for use with keyed arguments. Ordered
     * arguments may produce strange results.
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
            'arguments' => $this->arguments,
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
            'arguments' => $arguments,
            'aliases'   => $aliases,
            'callables' => $callables
        ] = $items->export();
        $this->arguments = $arguments;
        $this->aliases   = $aliases;
        $this->callables = $callables;
        if (is_array($settings) && count($settings) > 0) {
            $this->parseSettings($settings);
        }
    }
}
