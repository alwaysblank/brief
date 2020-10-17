<?php


namespace AlwaysBlank\Brief;


class Workers
{
    protected $workers;
    protected $current;

    public function __construct()
    {
        $this->workers = [];
    }

    /**
     * Add a callable to the repository.
     *
     * @param string $handle
     * @param        $callable
     *
     * @return $this
     */
    public function add(string $handle, $callable)
    {
        if (is_callable($callable)) {
            $this->workers[$handle] = $callable;
        }

        return $this;
    }

    /**
     * See if a handle exists in the repository.
     *
     * @param $handle
     *
     * @return bool
     */
    public function isSet($handle)
    {
        return isset($this->workers[$handle]);
    }

    /**
     * See if a handle is callable (and exists).
     *
     * @param $handle
     *
     * @return bool
     */
    public function isCallable($handle)
    {
        return $this->isSet($handle) && is_callable($this->workers[$handle]);
    }

    /**
     * Safely call something; returns null if the handle doesn't exist.
     *
     * @param string $handle
     * @param mixed  ...$arguments
     *
     * @return mixed|null
     */
    public function call(string $handle, ...$arguments)
    {
        if (isset($this->workers[$handle]) && is_callable($this->workers[$handle])) {
            return $this->workers[$handle](...$arguments);
        }

        return null;
    }
}
