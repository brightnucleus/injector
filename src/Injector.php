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
     * Cached contents of the config files.
     *
     * @since 0.1.0
     *
     * @var array
     */
    protected $configFilesCache = [];

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
     * - 'configFiles'
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
            $sharedAliases   = $config->hasKey('sharedAliases')
                ? $config->getKey('sharedAliases') : [];
            $standardAliases = $config->hasKey('standardAliases')
                ? $config->getKey('standardAliases') : [];
            $configFiles     = $config->hasKey('configFiles')
                ? $config->getKey('configFiles') : [];
            $aliases         = array_merge(
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
            array_walk($configFiles, [$this, 'defineConfigFiles']);
        } catch (Exception $exception) {
            throw new InvalidMappingsException(
                'Failed to set up dependency injector: '
                . $exception->getMessage()
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
     * Tell our Injector where to find the config files.
     *
     * @since 0.1.0
     *
     * @param string $configSetup Config setup from configuration file.
     * @param string $interface   Interface to register the config against.
     *
     * @throws \Auryn\InjectionException   If config instantiation fails.
     * @throws InvalidConfigException      If the config setup has no path.
     * @throws FailedToLoadConfigException If the config file could not be loaded.
     */
    protected function defineConfigFiles($configSetup, $interface)
    {
        if (! array_key_exists('path', $configSetup)) {
            throw new InvalidConfigException(
                sprintf(
                    _('Config file setup is missing path: "%1$s".'),
                    json_encode($configSetup)
                )
            );
        }
        $configData = $this->fetchConfigData($configSetup['path']);
        $config     = $this->make(
            'BrightNucleus\Config\ConfigInterface',
            [':config' => $configData]
        );
        $this->define(
            $interface,
            [
                ':config' => array_key_exists('subKey', $configSetup)
                    ? $config->getSubConfig($configSetup['subKey'])
                    : $config,
            ]
        );
    }

    /**
     * Get the data within a config file.
     *
     * We cache the loaded files to avoid loading a file multiple times.
     *
     * @since 0.1.0
     *
     * @param string $path Path to the config file.
     *
     * @return array Contents of the config file.
     * @throws FailedToLoadConfigException If the config file does not exist or is not readable.
     * @throws FailedToLoadConfigException If the config file could not be loaded.
     */
    protected function fetchConfigData($path)
    {
        if (! is_readable($path)) {
            throw new FailedToLoadConfigException(
                sprintf(
                    _('Config file could not be found: "%1$s".'),
                    json_encode($path)
                )
            );
        }

        if (! array_key_exists($path, $this->configFilesCache)) {
            $config                        = Loader::load($path);
            $this->configFilesCache[$path] = $config;
        }

        return (array)$this->configFilesCache[$path];
    }
}
