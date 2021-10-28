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

use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Class CachingReflector.
 *
 * @since   0.3.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class CachingReflector implements Reflector
{

    const CACHE_KEY_CLASSES     = 'bn.injector.refls.classes.';
    const CACHE_KEY_CTORS       = 'bn.injector.refls.ctors.';
    const CACHE_KEY_CTOR_PARAMS = 'bn.injector.refls.ctor-params.';
    const CACHE_KEY_FUNCS       = 'bn.injector.refls.funcs.';
    const CACHE_KEY_METHODS     = 'bn.injector.refls.methods.';

    private $reflector;
    private $cache;

    public function __construct(Reflector $reflector = null, ReflectionCache $cache = null)
    {
        $this->reflector = $reflector ?: new StandardReflector;
        $this->cache     = $cache ?: new ReflectionCacheArray;
    }

    /**
     * Retrieves ReflectionClass instances, caching them for future retrieval.
     *
     * @since 0.3.0
     *
     * @param string $class Class name to retrieve the ReflectionClass from.
     *
     * @return ReflectionClass ReflectionClass object for the specified class.
     */
    public function getClass($class)
    {
        $cacheKey = self::CACHE_KEY_CLASSES . strtolower($class);

        if (! $reflectionClass = $this->cache->fetch($cacheKey)) {
            $reflectionClass = new ReflectionClass($class);
            $this->cache->store($cacheKey, $reflectionClass);
        }

        return $reflectionClass;
    }

    /**
     * Retrieves and caches the constructor (ReflectionMethod) for the specified class.
     *
     * @since 0.3.0
     *
     * @param string $class Class name to retrieve the constructor from.
     *
     * @return ReflectionMethod ReflectionMethod for the constructor of the specified class.
     */
    public function getConstructor($class)
    {
        $cacheKey = self::CACHE_KEY_CTORS . strtolower($class);

        $reflectedConstructor = $this->cache->fetch($cacheKey);

        if ($reflectedConstructor === false) {
            $reflectionClass      = $this->getClass($class);
            $reflectedConstructor = $reflectionClass->getConstructor();
            $this->cache->store($cacheKey, $reflectedConstructor);
        }

        return $reflectedConstructor;
    }

    /**
     * Retrieves and caches an array of constructor parameters for the given class.
     *
     * @since 0.3.0
     *
     * @param string $class Class name to retrieve the constructor arguments from.
     *
     * @return ReflectionParameter[] Array of ReflectionParameter objects for the given class' constructor.
     */
    public function getConstructorParams($class)
    {
        $cacheKey = self::CACHE_KEY_CTOR_PARAMS . strtolower($class);

        $reflectedConstructorParams = $this->cache->fetch($cacheKey);

        if (false !== $reflectedConstructorParams) {
            return $reflectedConstructorParams;
        } elseif ($reflectedConstructor = $this->getConstructor($class)) {
            $reflectedConstructorParams = $reflectedConstructor->getParameters();
        } else {
            $reflectedConstructorParams = null;
        }

        $this->cache->store($cacheKey, $reflectedConstructorParams);

        return $reflectedConstructorParams;
    }

    /**
     * Retrieves the class type-hint from a given ReflectionParameter.
     *
     * There is no way to directly access a parameter's type-hint without instantiating a new ReflectionClass instance
     * and calling its getName() method. This method stores the results of this approach so that if the same parameter
     * type-hint or ReflectionClass is needed again we already have it cached.
     *
     * @since 0.3.0
     *
     * @param ReflectionFunctionAbstract $function Reflection object for the function.
     * @param ReflectionParameter        $param    Reflection object for the parameter.
     *
     * @return mixed Type-hint of the class. Null if none available.
     */
    public function getParamTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $param)
    {
        $lowParam = strtolower($param->name);

        if ($function instanceof ReflectionMethod) {
            $lowClass      = strtolower($function->class);
            $lowMethod     = strtolower($function->name);
            $paramCacheKey = self::CACHE_KEY_CLASSES . "{$lowClass}.{$lowMethod}.param-{$lowParam}";
        } else {
            $lowFunc       = strtolower($function->name);
            $paramCacheKey = ($lowFunc !== '{closure}')
                ? self::CACHE_KEY_FUNCS . ".{$lowFunc}.param-{$lowParam}"
                : null;
        }

        $typeHint = ($paramCacheKey === null) ? false : $this->cache->fetch($paramCacheKey);

        if (false !== $typeHint) {
            return $typeHint;
        }
        
        // Checking if the PHP version is 7 or higher, so we keep the compatibility
        if ( PHP_VERSION_ID >= 70000 ) {
            $reflectionClass = ($param->getType() && !$param->getType()->isBuiltin()) ? new ReflectionClass($param->getType()->getName()) : null;
        } else {
            // As $parameter->(has|get)Type() was only introduced with PHP 7.0+,
			// we need to provide a work-around for PHP 5.6 while we officially
			// support it.

            $reflectionClass = $param->getClass();
        }

        if ($reflectionClass !== null) {
            $typeHint      = $reflectionClass->getName();
            $classCacheKey = self::CACHE_KEY_CLASSES . strtolower($typeHint);
            $this->cache->store($classCacheKey, $reflectionClass);
        } else {
            $typeHint = null;
        }

        $this->cache->store($paramCacheKey, $typeHint);

        return $typeHint;
    }

    /**
     * Retrieves and caches a reflection for the specified function.
     *
     * @since 0.3.0
     *
     * @param string $functionName Name of the function to get a reflection for.
     *
     * @return ReflectionFunction ReflectionFunction object for the specified function.
     */
    public function getFunction($functionName)
    {
        $lowFunc  = strtolower($functionName);
        $cacheKey = self::CACHE_KEY_FUNCS . $lowFunc;

        $reflectedFunc = $this->cache->fetch($cacheKey);

        if (false === $reflectedFunc) {
            $reflectedFunc = new ReflectionFunction($functionName);
            $this->cache->store($cacheKey, $reflectedFunc);
        }

        return $reflectedFunc;
    }

    /**
     * Retrieves and caches a reflection for the specified class method.
     *
     * @since 0.3.0
     *
     * @param string|object $classNameOrInstance Class name or instance the method is referring to.
     * @param string        $methodName          Name of the method to get the reflection for.
     *
     * @return ReflectionMethod ReflectionMethod object for the specified method.
     */
    public function getMethod($classNameOrInstance, $methodName)
    {
        $className = is_string($classNameOrInstance)
            ? $classNameOrInstance
            : get_class($classNameOrInstance);

        $cacheKey = self::CACHE_KEY_METHODS . strtolower($className) . '.' . strtolower($methodName);

        if (! $reflectedMethod = $this->cache->fetch($cacheKey)) {
            $reflectedMethod = new ReflectionMethod($className, $methodName);
            $this->cache->store($cacheKey, $reflectedMethod);
        }

        return $reflectedMethod;
    }
}
