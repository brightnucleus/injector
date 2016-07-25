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
 * Class ReflectionCacheApc.
 *
 * @since   0.3.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class ReflectionCacheApc implements ReflectionCache
{

    /**
     * The private cache storage.
     *
     * @since 0.3.0
     *
     * @var ReflectionCacheArray
     */
    private $localCache;

    /**
     * The expiry time.
     *
     * @since 0.3.0
     *
     * @var int
     */
    private $timeToLive = 5;

    /**
     * Instantiate a ReflectionCacheApc object.
     *
     * @since 0.3.0
     *
     * @param ReflectionCache|null $localCache
     */
    public function __construct(ReflectionCache $localCache = null)
    {
        $this->localCache = $localCache ?: new ReflectionCacheArray;
    }

    /**
     * Set the expiry time.
     *
     * @since 0.3.0
     *
     * @param int $seconds The number of seconds that the cache value should live.
     *
     * @return $this Instance of the cache object.
     */
    public function setTimeToLive($seconds)
    {
        $seconds          = (int)$seconds;
        $this->timeToLive = ($seconds > 0) ? $seconds : $this->timeToLive;

        return $this;
    }

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
        $localData = $this->localCache->fetch($key);

        if ($localData != false) {
            return $localData;
        } else {
            $success = null; // stupid by-ref parameter that scrutinizer complains about
            $data    = apc_fetch($key, $success);

            return $success ? $data : false;
        }
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
        $this->localCache->store($key, $data);
        apc_store($key, $data, $this->timeToLive);
    }
}
