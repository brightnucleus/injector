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

use Auryn\Injector as AurynInjector;
use Auryn\Reflector;
use BrightNucleus\Config\ConfigInterface;
use BrightNucleus\Config\ConfigTrait;
use BrightNucleus\Config\Exception\FailedToLoadConfigException;
use BrightNucleus\Config\Exception\FailedToProcessConfigException;
use BrightNucleus\Config\Exception\InvalidConfigException;
use BrightNucleus\Config\Loader;
use BrightNucleus\Injector\Exception\InvalidMappingsException;
use Exception;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;

/**
 * Class InjectorService.
 *
 * Extends Auryn Injector to make it work with a Config. Config files are
 * cached, so that each individual file will only ever be loaded once.
 *
 * @since   0.1.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class Injector extends AurynInjector implements InjectorInterface
{

    use ConfigTrait;

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
     * @param Reflector|null  $reflector Optional. Reflector class to use for traversal. Falls back to
     *                                   Auryn\CachingReflector.
     *
     * @throws FailedToProcessConfigException If the config file could not be processed.
     * @throws InvalidMappingsException       If the definitions could not be registered.
     */
    public function __construct(ConfigInterface $config, Reflector $reflector = null)
    {
        parent::__construct($reflector);

        $this->processConfig($config);

        $this->registerMappings($this->config);
    }

    /**
     * Register mapping definitions.
     *
     * Takes a ConfigInterface and reads the following keys to add definitions:
     * - 'sharedAliases'
     * - 'standardAliases'
     * - 'argumentProviders'
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
        try {
            $sharedAliases       = $config->hasKey('sharedAliases')
                ? $config->getKey('sharedAliases') : [];
            $standardAliases     = $config->hasKey('standardAliases')
                ? $config->getKey('standardAliases') : [];
            $argumentDefinitions = $config->hasKey('argumentDefinitions')
                ? $config->getKey('argumentDefinitions') : [];
            $argumentProviders   = $config->hasKey('argumentProviders')
                ? $config->getKey('argumentProviders') : [];
            $aliases             = array_merge(
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
            array_walk($aliases, [$this, 'mapAliases']);
            array_walk($sharedAliases, [$this, 'shareAliases']);
            array_walk($argumentDefinitions, [$this, 'defineArguments']);
            array_walk($argumentProviders, [$this, 'defineArgumentProviders']);
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
     * @throws \Auryn\ConfigException
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
     * @throws \Auryn\ConfigException
     */
    protected function shareAliases($class, $interface)
    {
        $this->share($interface);
    }

    /**
     * Tell our Injector how arguments are defined.
     *
     * @since 0.2.0
     *
     * @param string $argumentSetup Argument providers setup from configuration file.
     * @param string $argument      The argument to provide.
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
        if ( null === $interface ) {
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
}
