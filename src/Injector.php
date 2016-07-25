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
use BrightNucleus\Config\ConfigTrait;
use BrightNucleus\Config\Exception\FailedToProcessConfigException;
use BrightNucleus\Injector\Exception\ConfigException;
use BrightNucleus\Injector\Exception\InjectionException;
use BrightNucleus\Injector\Exception\InjectorException;
use BrightNucleus\Injector\Exception\InvalidMappingsException;
use Closure;
use Exception;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Class Injector.
 *
 * @since   0.1.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class Injector implements InjectorInterface
{

    use ConfigTrait;

    const A_RAW      = ':';
    const A_DELEGATE = '+';
    const A_DEFINE   = '@';

    const I_BINDINGS  = 1;
    const I_DELEGATES = 2;
    const I_PREPARES  = 4;
    const I_ALIASES   = 8;
    const I_SHARES    = 16;
    const I_ALL       = 17;

    const K_STANDARD_ALIASES     = 'standardAliases';
    const K_SHARED_ALIASES       = 'sharedAliases';
    const K_ARGUMENT_DEFINITIONS = 'argumentDefinitions';
    const K_ARGUMENT_PROVIDERS   = 'argumentProviders';
    const K_DELEGATIONS          = 'delegations';
    const K_PREPARATIONS         = 'preparations';

    protected $reflector;
    protected $classDefinitions = [];
    protected $paramDefinitions = [];
    protected $aliases          = [];
    protected $shares           = [];
    protected $prepares         = [];
    protected $delegates        = [];
    protected $inProgressMakes  = [];

    /**
     * @var array Argument definitions for the injector to use.
     *
     * @since 0.2.0
     */
    protected $argumentDefinitions = [];

    /**
     * Instantiate a Injector object.
     *
     * @since 0.1.0
     *
     * @param ConfigInterface $config    Configuration settings to instantiate the Injector.
     * @param Reflector|null  $reflector Optional. Reflector class to use for traversal. Falls back to CachingReflector.
     *
     * @throws FailedToProcessConfigException If the config file could not be processed.
     * @throws InvalidMappingsException       If the definitions could not be registered.
     */
    public function __construct(ConfigInterface $config, Reflector $reflector = null)
    {
        $this->processConfig($config);
        $this->registerMappings($this->config);
        $this->reflector = $reflector ?: new CachingReflector;
    }

    /**
     * Don't share the instantiation chain across clones.
     *
     * @since 0.3.0
     */
    public function __clone()
    {
        $this->inProgressMakes = [];
    }

    /**
     * Register mapping definitions.
     *
     * Takes a ConfigInterface and reads the following keys to add definitions:
     * - 'sharedAliases'
     * - 'standardAliases'
     * - 'argumentDefinitions'
     * - 'argumentProviders'
     * - 'delegations'
     * - 'preparations'
     *
     * @since 0.1.0
     *
     * @param ConfigInterface $config Config file to parse.
     *
     * @throws InvalidMappingsException If a needed key could not be read from the config file.
     * @throws InvalidMappingsException If the dependency injector could not be set up.
     */
    public function registerMappings(ConfigInterface $config)
    {
        $configKeys = [
            static::K_STANDARD_ALIASES     => 'mapAliases',
            static::K_SHARED_ALIASES       => 'shareAliases',
            static::K_ARGUMENT_DEFINITIONS => 'defineArguments',
            static::K_ARGUMENT_PROVIDERS   => 'defineArgumentProviders',
            static::K_DELEGATIONS          => 'defineDelegations',
            static::K_PREPARATIONS         => 'definePreparations',
        ];
        try {
            foreach ($configKeys as $key => $method) {
                $$key = $config->hasKey($key)
                    ? $config->getKey($key) : [];
            }
            $standardAliases = array_merge(
                $sharedAliases,
                $standardAliases
            );
        } catch (Exception $exception) {
            throw new InvalidMappingsException(
                sprintf(
                    _('Failed to read needed keys from config. Reason: "%1$s".'),
                    $exception->getMessage()
                )
            );
        }

        try {
            foreach ($configKeys as $key => $method) {
                array_walk($$key, [$this, $method]);
            }
        } catch (Exception $exception) {
            throw new InvalidMappingsException(
                sprintf(
                    _('Failed to set up dependency injector. Reason: "%1$s".'),
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Map Interfaces to concrete classes for our Injector.
     *
     * @since 0.1.0
     *
     * @param string $class     Concrete implementation to instantiate.
     * @param string $interface Alias to register the implementation for.
     *
     * @throws ConfigException If the alias could not be created.
     */
    protected function mapAliases($class, $interface)
    {
        if ($class === $interface) {
            return;
        }
        $this->alias($interface, $class);
    }

    /**
     * Tell our Injector which interfaces to share across all requests.
     *
     * @since 0.1.0
     *
     * @param string $class     Concrete implementation to instantiate.
     * @param string $interface Alias to register the implementation for.
     *
     * @throws ConfigException If the interface could not be shared.
     */
    protected function shareAliases($class, $interface)
    {
        $this->share($interface);
    }

    /**
     * Tell our Injector how arguments are defined.
     *
     * @since 0.2.3
     *
     * @param array  $argumentSetup Argument providers setup from configuration file.
     * @param string $alias         The alias for which to define the argument.
     *
     * @throws InvalidMappingsException If a required config key could not be found.
     */
    protected function defineArguments($argumentSetup, $alias)
    {
        foreach ($argumentSetup as $key => $value) {
            $this->addArgumentDefinition($value, $alias, [$key, null]);
        }
    }

    /**
     * Tell our Injector what instantiations are delegated to factories.
     *
     * @since 0.3.0
     *
     * @param callable $factory Factory that will take care of the instantiation.
     * @param string   $alias   The alias for which to define the delegation.
     *
     * @throws ConfigException If the delegation could not be configured.
     */
    protected function defineDelegations(callable $factory, $alias)
    {
        $this->delegate($alias, $factory);
    }

    /**
     * Tell our Injector what preparations need to be done.
     *
     * @since 0.2.4
     *
     * @param callable $preparation Preparation to execute on instantiation.
     * @param string   $alias       The alias for which to define the preparation.
     *
     * @throws InvalidMappingsException If a required config key could not be found.
     * @throws InjectionException If the prepare statement was not valid.
     */
    protected function definePreparations(callable $preparation, $alias)
    {
        $this->prepare($alias, $preparation);
    }

    /**
     * Tell our Injector how to produce required arguments.
     *
     * @since 0.2.0
     *
     * @param string $argumentSetup Argument providers setup from configuration file.
     * @param string $argument      The argument to provide.
     *
     * @throws InvalidMappingsException If a required config key could not be found.
     */
    protected function defineArgumentProviders($argumentSetup, $argument)
    {
        if (! array_key_exists('mappings', $argumentSetup)) {
            throw new InvalidMappingsException(
                sprintf(
                    _('Failed to define argument providers for argument "%1$s". '
                      . 'Reason: The key "mappings" was not found.'),
                    $argument
                )
            );
        }

        array_walk(
            $argumentSetup['mappings'],
            [$this, 'addArgumentDefinition'],
            [$argument, $argumentSetup['interface'] ?: null]
        );
    }

    /**
     * Add a single argument definition.
     *
     * @since 0.2.0
     *
     * @param callable $callable Callable to execute when the argument is needed.
     * @param string   $alias    Alias to add the argument definition to.
     * @param string   $args     Additional arguments used for definition. Array containing $argument & $interface.
     *
     * @throws InvalidMappingsException If $callable is not a callable.
     */
    protected function addArgumentDefinition($callable, $alias, $args)
    {
        list($argument, $interface) = $args;

        $value = is_callable($callable)
            ? $this->getArgumentProxy($alias, $interface, $callable)
            : $callable;

        $argumentDefinition = array_key_exists($alias, $this->argumentDefinitions)
            ? $this->argumentDefinitions[$alias]
            : [];

        $argumentDefinition[":${argument}"] = $value;
        $this->argumentDefinitions[$alias]  = $argumentDefinition;

        $this->define($alias, $this->argumentDefinitions[$alias]);
    }

    /**
     * Get an argument proxy for a given alias to provide to the injector.
     *
     * @since 0.2.0
     *
     * @param string   $alias     Alias that needs the argument.
     * @param string   $interface Interface that the proxy implements.
     * @param callable $callable  Callable used to initialize the proxy.
     *
     * @return object Argument proxy to provide to the inspector.
     */
    protected function getArgumentProxy($alias, $interface, $callable)
    {
        if (null === $interface) {
            $interface = 'stdClass';
        }

        $factory     = new LazyLoadingValueHolderFactory();
        $initializer = function (
            & $wrappedObject,
            LazyLoadingInterface $proxy,
            $method,
            array $parameters,
            & $initializer
        ) use (
            $alias,
            $interface,
            $callable
        ) {
            $initializer   = null;
            $wrappedObject = $callable($alias, $interface);

            return true;
        };

        return $factory->createProxy($interface, $initializer);
    }

    /**
     * Define instantiation directives for the specified class
     *
     * @param string $name The class (or alias) whose constructor arguments we wish to define
     * @param array  $args An array mapping parameter names to values/instructions
     *
     * @return InjectorInterface
     */
    public function define($name, array $args)
    {
        list(, $normalizedName) = $this->resolveAlias($name);
        $this->classDefinitions[$normalizedName] = $args;

        return $this;
    }

    /**
     * Assign a global default value for all parameters named $paramName
     *
     * Global parameter definitions are only used for parameters with no typehint, pre-defined or
     * call-time definition.
     *
     * @param string $paramName The parameter name for which this value applies
     * @param mixed  $value     The value to inject for this parameter name
     *
     * @return InjectorInterface
     */
    public function defineParam($paramName, $value)
    {
        $this->paramDefinitions[$paramName] = $value;

        return $this;
    }

    /**
     * Define an alias for all occurrences of a given typehint
     *
     * Use this method to specify implementation classes for interface and abstract class typehints.
     *
     * @param string $original The typehint to replace
     * @param string $alias    The implementation name
     *
     * @throws ConfigException if any argument is empty or not a string
     * @return InjectorInterface
     */
    public function alias($original, $alias)
    {
        if (empty($original) || ! is_string($original)) {
            throw new ConfigException(
                InjectorException::M_NON_EMPTY_STRING_ALIAS,
                InjectorException::E_NON_EMPTY_STRING_ALIAS
            );
        }
        if (empty($alias) || ! is_string($alias)) {
            throw new ConfigException(
                InjectorException::M_NON_EMPTY_STRING_ALIAS,
                InjectorException::E_NON_EMPTY_STRING_ALIAS
            );
        }

        $originalNormalized = $this->normalizeName($original);

        if (isset($this->shares[$originalNormalized])) {
            throw new ConfigException(
                sprintf(
                    InjectorException::M_SHARED_CANNOT_ALIAS,
                    $this->normalizeName(get_class($this->shares[$originalNormalized])),
                    $alias
                ),
                InjectorException::E_SHARED_CANNOT_ALIAS
            );
        }

        if (array_key_exists($originalNormalized, $this->shares)) {
            $aliasNormalized                = $this->normalizeName($alias);
            $this->shares[$aliasNormalized] = null;
            unset($this->shares[$originalNormalized]);
        }

        $this->aliases[$originalNormalized] = $alias;

        return $this;
    }

    protected function normalizeName($className)
    {
        return ltrim($className, '\\');
    }

    /**
     * Share the specified class/instance across the Injector context
     *
     * @param mixed $nameOrInstance The class or object to share
     *
     * @throws ConfigException if $nameOrInstance is not a string or an object
     * @return InjectorInterface
     */
    public function share($nameOrInstance)
    {
        if (is_string($nameOrInstance)) {
            $this->shareClass($nameOrInstance);
        } elseif (is_object($nameOrInstance)) {
            $this->shareInstance($nameOrInstance);
        } else {
            throw new ConfigException(
                sprintf(
                    InjectorException::M_SHARE_ARGUMENT,
                    __CLASS__,
                    gettype($nameOrInstance)
                ),
                InjectorException::E_SHARE_ARGUMENT
            );
        }

        return $this;
    }

    protected function shareClass($nameOrInstance)
    {
        list(, $normalizedName) = $this->resolveAlias($nameOrInstance);
        $this->shares[$normalizedName] = isset($this->shares[$normalizedName])
            ? $this->shares[$normalizedName]
            : null;
    }

    protected function resolveAlias($name)
    {
        $normalizedName = $this->normalizeName($name);
        if (isset($this->aliases[$normalizedName])) {
            $name           = $this->aliases[$normalizedName];
            $normalizedName = $this->normalizeName($name);
        }

        return array($name, $normalizedName);
    }

    protected function shareInstance($obj)
    {
        $normalizedName = $this->normalizeName(get_class($obj));
        if (isset($this->aliases[$normalizedName])) {
            // You cannot share an instance of a class name that is already aliased
            throw new ConfigException(
                sprintf(
                    InjectorException::M_ALIASED_CANNOT_SHARE,
                    $normalizedName,
                    $this->aliases[$normalizedName]
                ),
                InjectorException::E_ALIASED_CANNOT_SHARE
            );
        }
        $this->shares[$normalizedName] = $obj;
    }

    /**
     * Register a prepare callable to modify/prepare objects of type $name after instantiation
     *
     * Any callable or provisionable invokable may be specified. Preparers are passed two
     * arguments: the instantiated object to be mutated and the current Injector instance.
     *
     * @param string $name
     * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @throws InjectionException if $callableOrMethodStr is not a callable.
     *                            See https://github.com/rdlowrey/auryn#injecting-for-execution
     * @return InjectorInterface
     */
    public function prepare($name, $callableOrMethodStr)
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                InjectorException::E_INVOKABLE,
                $callableOrMethodStr
            );
        }

        list(, $normalizedName) = $this->resolveAlias($name);
        $this->prepares[$normalizedName] = $callableOrMethodStr;

        return $this;
    }

    protected function isExecutable($exe)
    {
        if (is_callable($exe)) {
            return true;
        }
        if (is_string($exe) && method_exists($exe, '__invoke')) {
            return true;
        }
        if (is_array($exe) && isset($exe[0], $exe[1]) && method_exists($exe[0], $exe[1])) {
            return true;
        }

        return false;
    }

    /**
     * Delegate the creation of $name instances to the specified callable
     *
     * @param string $name
     * @param mixed  $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @throws ConfigException if $callableOrMethodStr is not a callable.
     * @return InjectorInterface
     */
    public function delegate($name, $callableOrMethodStr)
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            $errorDetail = '';
            if (is_string($callableOrMethodStr)) {
                $errorDetail = " but received '$callableOrMethodStr'";
            } elseif (is_array($callableOrMethodStr) &&
                      array_key_exists(0, $callableOrMethodStr) &&
                      array_key_exists(1, $callableOrMethodStr) &&
                      count($callableOrMethodStr) === 2
            ) {
                if (is_string($callableOrMethodStr[0]) && is_string($callableOrMethodStr[1])) {
                    $errorDetail = " but received ['" . $callableOrMethodStr[0] . "', '" . $callableOrMethodStr[1] . "']";
                }
            }
            throw new ConfigException(
                sprintf(InjectorException::M_DELEGATE_ARGUMENT, __CLASS__, $errorDetail),
                InjectorException::E_DELEGATE_ARGUMENT
            );
        }
        $normalizedName                   = $this->normalizeName($name);
        $this->delegates[$normalizedName] = $callableOrMethodStr;

        return $this;
    }

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
    public function inspect($nameFilter = null, $typeFilter = null)
    {
        $result = [];
        $name   = $nameFilter ? $this->normalizeName($nameFilter) : null;

        if (empty($typeFilter)) {
            $typeFilter = static::I_ALL;
        }

        $types = [
            static::I_BINDINGS  => 'classDefinitions',
            static::I_DELEGATES => 'delegates',
            static::I_PREPARES  => 'prepares',
            static::I_ALIASES   => 'aliases',
            static::I_SHARES    => 'shares',
        ];

        foreach ($types as $type => $source) {
            if ($typeFilter & $type) {
                $result[$type] = $this->filter($this->{$source}, $name);
            }
        }

        return $result;
    }

    protected function filter($source, $name)
    {
        if (empty($name)) {
            return $source;
        } elseif (array_key_exists($name, $source)) {
            return array($name => $source[$name]);
        } else {
            return [];
        }
    }

    /**
     * Instantiate/provision a class instance
     *
     * @param string $name Name of an interface/class/alias to instantiate.
     * @param array  $args Optional arguments to pass to the object.
     *
     * @return mixed
     * @throws InjectionException If a cyclic dependency is detected.
     */
    public function make($name, array $args = [])
    {
        list($className, $normalizedClass) = $this->resolveAlias($name);

        if (isset($this->inProgressMakes[$normalizedClass])) {
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(
                    InjectorException::M_CYCLIC_DEPENDENCY,
                    $className
                ),
                InjectorException::E_CYCLIC_DEPENDENCY
            );
        }

        $this->inProgressMakes[$normalizedClass] = count($this->inProgressMakes);

        // isset() is used specifically here because classes may be marked as "shared" before an
        // instance is stored. In these cases the class is "shared," but it has a null value and
        // instantiation is needed.
        if (isset($this->shares[$normalizedClass])) {
            unset($this->inProgressMakes[$normalizedClass]);

            return $this->shares[$normalizedClass];
        }

        if (isset($this->delegates[$normalizedClass])) {
            $executable         = $this->buildExecutable($this->delegates[$normalizedClass]);
            $reflectionFunction = $executable->getCallableReflection();
            $args               = $this->provisionFuncArgs($reflectionFunction, $args);
            $obj                = call_user_func_array(array($executable, '__invoke'), $args);
        } else {
            $obj = $this->provisionInstance($className, $normalizedClass, $args);
        }

        $obj = $this->prepareInstance($obj, $normalizedClass);

        if (array_key_exists($normalizedClass, $this->shares)) {
            $this->shares[$normalizedClass] = $obj;
        }

        unset($this->inProgressMakes[$normalizedClass]);

        return $obj;
    }

    protected function provisionInstance($className, $normalizedClass, array $definition)
    {
        try {
            $ctor = $this->reflector->getConstructor($className);

            if (! $ctor) {
                $obj = $this->instantiateWithoutConstructorParams($className);
            } elseif (! $ctor->isPublic()) {
                throw new InjectionException(
                    $this->inProgressMakes,
                    sprintf(InjectorException::M_NON_PUBLIC_CONSTRUCTOR, $className),
                    InjectorException::E_NON_PUBLIC_CONSTRUCTOR
                );
            } elseif ($ctorParams = $this->reflector->getConstructorParams($className)) {
                $reflClass  = $this->reflector->getClass($className);
                $definition = isset($this->classDefinitions[$normalizedClass])
                    ? array_replace($this->classDefinitions[$normalizedClass], $definition)
                    : $definition;
                $args       = $this->provisionFuncArgs($ctor, $definition, $ctorParams);
                $obj        = $reflClass->newInstanceArgs($args);
            } else {
                $obj = $this->instantiateWithoutConstructorParams($className);
            }

            return $obj;
        } catch (ReflectionException $e) {
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(InjectorException::M_MAKE_FAILURE, $className, $e->getMessage()),
                InjectorException::E_MAKE_FAILURE,
                $e
            );
        }
    }

    protected function instantiateWithoutConstructorParams($className)
    {
        $reflClass = $this->reflector->getClass($className);

        if (! $reflClass->isInstantiable()) {
            $type = $reflClass->isInterface() ? 'interface' : 'abstract class';
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(InjectorException::M_NEEDS_DEFINITION, $type, $className),
                InjectorException::E_NEEDS_DEFINITION
            );
        }

        return new $className;
    }

    protected function provisionFuncArgs(
        ReflectionFunctionAbstract $reflFunc,
        array $definition,
        array $reflParams = null
    ) {
        $args = [];

        // @TODO store this in ReflectionStorage
        if (! isset($reflParams)) {
            $reflParams = $reflFunc->getParameters();
        }

        foreach ($reflParams as $i => $reflParam) {
            $name = $reflParam->name;

            if (isset($definition[$i]) || array_key_exists($i, $definition)) {
                // indexed arguments take precedence over named parameters
                $arg = $definition[$i];
            } elseif (isset($definition[$name]) || array_key_exists($name, $definition)) {
                // interpret the param as a class name to be instantiated
                $arg = $this->make($definition[$name]);
            } elseif (($prefix = static::A_RAW . $name) && (isset($definition[$prefix]) || array_key_exists($prefix,
                        $definition))
            ) {
                // interpret the param as a raw value to be injected
                $arg = $definition[$prefix];
            } elseif (($prefix = static::A_DELEGATE . $name) && isset($definition[$prefix])) {
                // interpret the param as an invokable delegate
                $arg = $this->buildArgFromDelegate($name, $definition[$prefix]);
            } elseif (($prefix = static::A_DEFINE . $name) && isset($definition[$prefix])) {
                // interpret the param as a class definition
                $arg = $this->buildArgFromParamDefineArr($definition[$prefix]);
            } elseif (! $arg = $this->buildArgFromTypeHint($reflFunc, $reflParam)) {
                $arg = $this->buildArgFromReflParam($reflParam);
            }

            $args[] = $arg;
        }

        return $args;
    }

    protected function buildArgFromParamDefineArr($definition)
    {
        if (! is_array($definition)) {
            throw new InjectionException(
                $this->inProgressMakes
            // @TODO Add message
            );
        }

        if (! isset($definition[0], $definition[1])) {
            throw new InjectionException(
                $this->inProgressMakes
            // @TODO Add message
            );
        }

        list($class, $definition) = $definition;

        return $this->make($class, $definition);
    }

    protected function buildArgFromDelegate($paramName, $callableOrMethodStr)
    {
        if ($this->isExecutable($callableOrMethodStr) === false) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        $executable = $this->buildExecutable($callableOrMethodStr);

        return $executable($paramName, $this);
    }

    protected function buildArgFromTypeHint(ReflectionFunctionAbstract $reflFunc, ReflectionParameter $reflParam)
    {
        $typeHint = $this->reflector->getParamTypeHint($reflFunc, $reflParam);

        if (! $typeHint) {
            $obj = null;
        } elseif ($reflParam->isDefaultValueAvailable()) {
            $normalizedName = $this->normalizeName($typeHint);
            // Injector has been told explicitly how to make this type
            if (isset($this->aliases[$normalizedName]) ||
                isset($this->delegates[$normalizedName]) ||
                isset($this->shares[$normalizedName])
            ) {
                $obj = $this->make($typeHint);
            } else {
                $obj = $reflParam->getDefaultValue();
            }
        } else {
            $obj = $this->make($typeHint);
        }

        return $obj;
    }

    protected function buildArgFromReflParam(ReflectionParameter $reflParam)
    {
        if (array_key_exists($reflParam->name, $this->paramDefinitions)) {
            $arg = $this->paramDefinitions[$reflParam->name];
        } elseif ($reflParam->isDefaultValueAvailable()) {
            $arg = $reflParam->getDefaultValue();
        } elseif ($reflParam->isOptional()) {
            // This branch is required to work around PHP bugs where a parameter is optional
            // but has no default value available through reflection. Specifically, PDO exhibits
            // this behavior.
            $arg = null;
        } else {
            $reflFunc  = $reflParam->getDeclaringFunction();
            $classWord = ($reflFunc instanceof ReflectionMethod)
                ? $reflFunc->getDeclaringClass()->name . '::'
                : '';
            $funcWord  = $classWord . $reflFunc->name;

            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(
                    InjectorException::M_UNDEFINED_PARAM,
                    $reflParam->name,
                    $reflParam->getPosition(),
                    $funcWord
                ),
                InjectorException::E_UNDEFINED_PARAM
            );
        }

        return $arg;
    }

    protected function prepareInstance($obj, $normalizedClass)
    {
        if (isset($this->prepares[$normalizedClass])) {
            $prepare    = $this->prepares[$normalizedClass];
            $executable = $this->buildExecutable($prepare);
            $result     = $executable($obj, $this);
            if ($result instanceof $normalizedClass) {
                $obj = $result;
            }
        }

        $interfaces = @class_implements($obj);

        if ($interfaces === false) {
            throw new InjectionException(
                $this->inProgressMakes,
                sprintf(
                    InjectorException::M_MAKING_FAILED,
                    $normalizedClass,
                    gettype($obj)
                ),
                InjectorException::E_MAKING_FAILED
            );
        }

        if (empty($interfaces)) {
            return $obj;
        }

        $interfaces = array_flip(array_map(array($this, 'normalizeName'), $interfaces));
        $prepares   = array_intersect_key($this->prepares, $interfaces);
        foreach ($prepares as $interfaceName => $prepare) {
            $executable = $this->buildExecutable($prepare);
            $result     = $executable($obj, $this);
            if ($result instanceof $normalizedClass) {
                $obj = $result;
            }
        }

        return $obj;
    }

    /**
     * Invoke the specified callable or class::method string, provisioning dependencies along the way
     *
     * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
     * @param array $args                Optional array specifying params with which to invoke the provisioned callable
     *
     * @return mixed Returns the invocation result returned from calling the generated executable
     */
    public function execute($callableOrMethodStr, array $args = [])
    {
        list($reflFunc, $invocationObj) = $this->buildExecutableStruct($callableOrMethodStr);
        $executable = new Executable($reflFunc, $invocationObj);
        $args       = $this->provisionFuncArgs($reflFunc, $args);

        return call_user_func_array(array($executable, '__invoke'), $args);
    }

    /**
     * Provision an Executable instance from any valid callable or class::method string
     *
     * @param mixed $callableOrMethodStr A valid PHP callable or a provisionable ClassName::methodName string
     *
     * @return Executable
     * @throws InjectionException If the Executable structure could not be built.
     */
    public function buildExecutable($callableOrMethodStr)
    {
        try {
            list($reflFunc, $invocationObj) = $this->buildExecutableStruct($callableOrMethodStr);
        } catch (ReflectionException $e) {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr,
                $e
            );
        }

        return new Executable($reflFunc, $invocationObj);
    }

    protected function buildExecutableStruct($callableOrMethodStr)
    {
        if (is_string($callableOrMethodStr)) {
            $executableStruct = $this->buildExecutableStructFromString($callableOrMethodStr);
        } elseif ($callableOrMethodStr instanceof Closure) {
            $callableRefl     = new ReflectionFunction($callableOrMethodStr);
            $executableStruct = array($callableRefl, null);
        } elseif (is_object($callableOrMethodStr) && is_callable($callableOrMethodStr)) {
            $invocationObj    = $callableOrMethodStr;
            $callableRefl     = $this->reflector->getMethod($invocationObj, '__invoke');
            $executableStruct = array($callableRefl, $invocationObj);
        } elseif (is_array($callableOrMethodStr)
                  && isset($callableOrMethodStr[0], $callableOrMethodStr[1])
                  && count($callableOrMethodStr) === 2
        ) {
            $executableStruct = $this->buildExecutableStructFromArray($callableOrMethodStr);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $callableOrMethodStr
            );
        }

        return $executableStruct;
    }

    protected function buildExecutableStructFromString($stringExecutable)
    {
        if (function_exists($stringExecutable)) {
            $callableRefl     = $this->reflector->getFunction($stringExecutable);
            $executableStruct = array($callableRefl, null);
        } elseif (method_exists($stringExecutable, '__invoke')) {
            $invocationObj    = $this->make($stringExecutable);
            $callableRefl     = $this->reflector->getMethod($invocationObj, '__invoke');
            $executableStruct = array($callableRefl, $invocationObj);
        } elseif (strpos($stringExecutable, '::') !== false) {
            list($class, $method) = explode('::', $stringExecutable, 2);
            $executableStruct = $this->buildStringClassMethodCallable($class, $method);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $stringExecutable
            );
        }

        return $executableStruct;
    }

    protected function buildStringClassMethodCallable($class, $method)
    {
        $relativeStaticMethodStartPos = strpos($method, 'parent::');

        if ($relativeStaticMethodStartPos === 0) {
            $childReflection = $this->reflector->getClass($class);
            $class           = $childReflection->getParentClass()->name;
            $method          = substr($method, $relativeStaticMethodStartPos + 8);
        }

        list($className, $normalizedClass) = $this->resolveAlias($class);
        $reflectionMethod = $this->reflector->getMethod($className, $method);

        if ($reflectionMethod->isStatic()) {
            return array($reflectionMethod, null);
        }

        $instance = $this->make($className);
        // If the class was delegated, the instance may not be of the type
        // $class but some other type. We need to get the reflection on the
        // actual class to be able to call the method correctly.
        $reflectionMethod = $this->reflector->getMethod($instance, $method);

        return array($reflectionMethod, $instance);
    }

    protected function buildExecutableStructFromArray($arrayExecutable)
    {
        list($classOrObj, $method) = $arrayExecutable;

        if (is_object($classOrObj) && method_exists($classOrObj, $method)) {
            $callableRefl     = $this->reflector->getMethod($classOrObj, $method);
            $executableStruct = array($callableRefl, $classOrObj);
        } elseif (is_string($classOrObj)) {
            $executableStruct = $this->buildStringClassMethodCallable($classOrObj, $method);
        } else {
            throw InjectionException::fromInvalidCallable(
                $this->inProgressMakes,
                $arrayExecutable
            );
        }

        return $executableStruct;
    }

    /**
     * Get the chain of instantiations.
     *
     * @since 0.3.0
     *
     * @return InjectionChain Chain of instantiations.
     */
    public function getInjectionChain()
    {
        return new InjectionChain($this->inProgressMakes);
    }
}
