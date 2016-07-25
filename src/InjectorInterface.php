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

use BrightNucleus\Config\ConfigInterface;

/**
 * Interface InjectorInterface.
 *
 * @since   0.1.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
interface InjectorInterface
{

    /**
     * Define instantiation directives for the specified class
     *
     * @param string $name The class (or alias) whose constructor arguments we wish to define
     * @param array  $args An array mapping parameter names to values/instructions
     *
     * @return InjectorInterface
     */
    public function define($name, array $args);

    /**
     * Assign a global default value for all parameters named $paramName
     *
     * Global parameter definitions are only used for parameters with no typehint, pre-defined or call-time definition.
     *
     * @param string $paramName The parameter name for which this value applies
     * @param mixed  $value     The value to inject for this parameter name
     *
     * @return InjectorInterface
     */
    public function defineParam($paramName, $value);

    /**
     * Define an alias for all occurrences of a given typehint
     *
     * Use this method to specify implementation classes for interface and abstract class typehints.
     *
     * @param string $original The typehint to replace
     * @param string $alias    The implementation name
     *
     * @return InjectorInterface
     */
    public function alias($original, $alias);

    /**
     * Share the specified class/instance across the Injector context
     *
     * @param mixed $nameOrInstance The class or object to share
     *
     * @return InjectorInterface
     */
    public function share($nameOrInstance);

    /**
     * Register a prepare callable to modify/prepare objects of type $name after instantiation
     *
     * Any callable or provisionable invokable may be specified. Preparers are passed two arguments: the instantiated
     * object to be mutated and the current Injector instance.
     *
     * @param string $name                Name of an interface/class/alias to prepare.
     * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @return InjectorInterface
     */
    public function prepare($name, $callableOrMethodStr);

    /**
     * Delegate the creation of $name instances to the specified callable
     *
     * @param string $name                Name of an interface/class/alias to delegate.
     * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @return InjectorInterface
     */
    public function delegate($name, $callableOrMethodStr);

    /**
     * Retrieve stored data for the specified definition type
     *
     * Exposes introspection of existing binds/delegates/shares/etc for decoration and composition.
     *
     * @param string $nameFilter An optional class name filter
     * @param int    $typeFilter A bitmask of Injector::* type constant flags
     *
     * @return array
     */
    public function inspect($nameFilter = null, $typeFilter = null);

    /**
     * Instantiate/provision a class instance
     *
     * @param string $name Name of an interface/class/alias to instantiate.
     * @param array  $args Optional arguments to pass to the object.
     *
     * @return mixed
     */
    public function make($name, array $args = []);

    /**
     * Invoke the specified callable or class::method string, provisioning dependencies along the way
     *
     * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
     * @param array $args                Optional array specifying params with which to invoke the provisioned callable
     *
     * @return mixed Returns the invocation result returned from calling the generated executable
     */
    public function execute($callableOrMethodStr, array $args = []);

    /**
     * Register mapping definitions.
     *
     * Takes a ConfigInterface and reads the following keys to add definitions:
     * - 'sharedAliases'
     * - 'standardAliases'
     * - 'argumentDefinitions'
     * - 'argumentProviders'
     * - 'preparations'
     *
     * @since 0.1.0
     *
     * @param ConfigInterface $config Config file to parse.
     */
    public function registerMappings(ConfigInterface $config);

    /**
     * Get the chain of instantiations.
     *
     * @since 0.3.0
     *
     * @return InjectionChain Chain of instantiations.
     */
    public function getInjectionChain();
}
