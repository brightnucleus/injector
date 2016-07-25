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
 * Class StandardReflector.
 *
 * @since   0.3.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class StandardReflector implements Reflector
{

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
        return new ReflectionClass($class);
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
        $reflectionClass = new ReflectionClass($class);

        return $reflectionClass->getConstructor();
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
        return ($reflectedConstructor = $this->getConstructor($class))
            ? $reflectedConstructor->getParameters()
            : null;
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
        return ($reflectionClass = $param->getClass())
            ? $reflectionClass->getName()
            : null;
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
        return new ReflectionFunction($functionName);
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

        return new ReflectionMethod($className, $methodName);
    }
}
