<?php declare(strict_types = 1);
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
 * Class Injection.
 *
 * This class is used to make it clear to the injector that a definition is
 * being set to a class string to be instantiated, not a string to leave as is.
 *
 * @since   0.4.1
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class Injection
{
    /**
     * Alias that should be instantiated.
     *
     * @since 0.4.1
     *
     * @var string
     */
    private $alias;

    /**
     * Instantiate an Injection object.
     *
     * @since 0.4.1
     *
     * @param string $alias Alias that should be instantiated.
     */
    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

    /**
     * Get the alias that should be instantiated.
     *
     * @since 0.4.1
     *
     * @return string Alias that should be instantiated.
     */
    public function getAlias()
    {
        return $this->alias;
    }
}
