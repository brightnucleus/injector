<?php
/**
 * Bright Nucleus Injector Component.
 *
 * @package   BrightNucleus\Injector
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      http://www.brightnucleus.com/
 * @copyright 2016 Alain Schlesser, Bright Nucleus
 */

namespace BrightNucleus\Injector;

/**
 * Class ReflectionCacheArray.
 *
 * @since   0.3.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class ReflectionCacheArray implements ReflectionCache
{

    /**
     * Internal cache storage.
     *
     * @var array
     *
     * @since 0.3.0
     */
    private $cache = [];

    /**
     * Fetch a key from the cache.
     *
     * @since 0.3.0
     *
     * @param string $key The key to fetch.
     *
     * @return mixed|false Value of the key in the cache, or false if not found.
     */
    public function fetch($key)
    {
        // The additional isset() check here improves performance but we also
        // need array_key_exists() because some cached values === NULL.
        return (isset($this->cache[$key]) || array_key_exists($key, $this->cache))
            ? $this->cache[$key]
            : false;
    }

    /**
     * Store the value for a specified key in the cache.
     *
     * @since 0.3.0
     *
     * @param string $key  The key for which to store the value.
     * @param mixed  $data The value to store under the specified key.
     */
    public function store($key, $data)
    {
        $this->cache[$key] = $data;
    }
}
