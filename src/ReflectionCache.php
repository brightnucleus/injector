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
 * Interface ReflectionCache.
 *
 * @since   0.3.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
interface ReflectionCache
{

    /**
     * Fetch a key from the cache.
     *
     * @since 0.3.0
     *
     * @param string $key The key to fetch.
     *
     * @return mixed|false Value of the key in the cache, or false if not found.
     */
    public function fetch($key);

    /**
     * Store the value for a specified key in the cache.
     *
     * @since 0.3.0
     *
     * @param string $key  The key for which to store the value.
     * @param mixed  $data The value to store under the specified key.
     */
    public function store($key, $data);
}
