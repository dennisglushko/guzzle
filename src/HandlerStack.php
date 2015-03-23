<?php
namespace GuzzleHttp;

/**
 * Creates a composed Guzzle handler function by stacking middlewares on top of
 * an HTTP handler function.
 */
class HandlerStack
{
    /** @var callable */
    private $handler;

    /** @var \SplStack */
    private $stack;

    /**
     * @param callable $handler Underlying HTTP handler.
     */
    public function __construct(callable $handler = null)
    {
        $this->handler = $handler;
        $this->stack = new \SplStack();
    }

    /**
     * Ensure the stack is cloned.
     */
    public function __clone()
    {
        $this->stack = clone $this->stack;
    }

    /**
     * Dumps a string representation of the stack.
     *
     * @return string
     */
    public function __toString()
    {
        $depth = 0;
        $stack = [];
        if ($this->stack) {
            $stack[] = "0) Handler: " . $this->debugCallable($this->handler);
        }

        $result = '';
        foreach ($this->stack as $tuple) {
            $depth++;
            $str = "{$depth}) Name: '{$tuple[1]}', ";
            $str .= "Function: " . $this->debugCallable($tuple[0]);
            $result = "> {$str}\n{$result}";
            $stack[] = $str;
        }

        foreach (array_keys($stack) as $k) {
            $result .= "< {$stack[$k]}\n";
        }

        return $result;
    }

    /**
     * Set the HTTP handler that actually returns a promise.
     *
     * @param callable $handler Accepts a request and array of options and
     *                          returns a Promise.
     */
    public function setHandler(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Returns true if the builder has a handler.
     *
     * @return bool
     */
    public function hasHandler()
    {
        return (bool) $this->handler;
    }

    /**
     * Unshift a middleware to the bottom of the stack.
     *
     * @param callable $middleware Middleware function
     * @param string   $name       Name to register for this middleware.
     */
    public function unshift(callable $middleware, $name = null)
    {
        $this->stack->unshift([$middleware, $name]);
    }

    /**
     * Push a middleware to the top of the stack.
     *
     * @param callable $middleware Middleware function
     * @param string   $name       Name to register for this middleware.
     */
    public function push(callable $middleware, $name = '')
    {
        $this->stack->push([$middleware, $name]);
    }

    /**
     * Add a middleware before another middleware by name.
     *
     * @param string   $findName   Middleware to find
     * @param callable $middleware Middleware function
     * @param string   $withName   Name to register for this middleware.
     */
    public function before($findName, callable $middleware, $withName = '')
    {
        $this->splice($findName, $withName, $middleware, true);
    }

    /**
     * Add a middleware after another middleware by name.
     *
     * @param string   $findName   Middleware to find
     * @param callable $middleware Middleware function
     * @param string   $withName   Name to register for this middleware.
     */
    public function after($findName, callable $middleware, $withName = '')
    {
        $this->splice($findName, $withName, $middleware, false);
    }

    /**
     * Remove a middleware by instance or name from the stack.
     *
     * @param callable|string $remove Middleware to remove by instance or name.
     */
    public function remove($remove)
    {
        $idx = is_callable($remove) ? 0 : 1;
        $newStack = new \SplStack();

        foreach ($this->stack as $i => $tuple) {
            if ($tuple[$idx] !== $remove) {
                $newStack->unshift($tuple);
            }
        }

        $this->stack = $newStack;
    }

    /**
     * Compose the middleware and handler into a single callable function.
     *
     * @return callable
     */
    public function resolve()
    {
        if (!($prev = $this->handler)) {
            throw new \LogicException('No handler has been specified');
        }

        foreach ($this->stack as $fn) {
            $prev = $fn[0]($prev);
        }

        return $prev;
    }

    /**
     * @param $name
     * @return int
     */
    private function findByName($name)
    {
        foreach ($this->stack as $k => $v) {
            if ($v[1] === $name) {
                return $k;
            }
        }

        throw new \InvalidArgumentException("Middleware not found: $name");
    }

    /**
     * Splices a function into the middleware list at a specific position.
     *
     * @param          $findName
     * @param          $withName
     * @param callable $middleware
     * @param          $before
     */
    private function splice($findName, $withName, callable $middleware, $before)
    {
        $idx = $this->findByName($findName);
        $tuple = [$middleware, $withName];

        if ($before) {
            if ($idx === 0) {
                $this->stack->unshift($tuple);
            } else {
                $this->stack->add($idx, $tuple);
            }
        } elseif ($idx === count($this->stack) - 1) {
            $this->stack->push($tuple);
        } else {
            $this->stack->add($idx, $tuple);
        }
    }

    /**
     * Provides a debug string for a given callable.
     *
     * @param array|callable $fn Function to write as a string.
     *
     * @return string
     */
    private function debugCallable($fn)
    {
        if (is_string($fn)) {
            return "callable({$fn})";
        }

        if (is_array($fn)) {
            return is_string($fn[0])
                ? "callable({$fn[0]}::{$fn[1]})"
                : "callable(['" . get_class($fn[0]) . "', '{$fn[1]}'])";
        }

        return 'callable(' . spl_object_hash($fn) . ')';
    }
}
