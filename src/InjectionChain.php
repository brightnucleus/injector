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

use BrightNucleus\Exception\RuntimeException;

/**
 * Class InjectionChain.
 *
 * @since   0.3.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class InjectionChain
{

    /**
     * Store the chain of instantiations.
     *
     * @var array
     *
     * @since 0.3.0
     */
    private $chain;

    /**
     * Instantiate an InjectionChain object.
     *
     * @since 0.3.0
     *
     * @param array|null $inProgressMakes Optional. Array of instantiations.
     */
    public function __construct(array $inProgressMakes = [])
    {
        $this->chain = array_flip($inProgressMakes);
    }

    /**
     * Get the chain of instantiations.
     *
     * @since 0.3.0
     *
     * @return array Array of instantiations.
     */
    public function getChain()
    {
        return $this->chain;
    }

    /**
     * Get the instantiation at a specific index.
     *
     * The first (root) instantiation is 0, with each subsequent level adding 1
     * more to the index.
     *
     * Provide a negative index to step back from the end of the chain.
     * Example: `getByIndex( -2 )` will return the second-to-last element.
     *
     * @since 0.3.0
     *
     * @param int $index Element index to retrieve. Negative value to fetch from the end of the chain.
     *
     * @return string|false Class name of the element at the specified index. False if index not found.
     * @throws RuntimeException If the index is not a numeric value.
     */
    public function getByIndex($index)
    {
        if (! is_numeric($index)) {
            throw new RuntimeException('Index needs to be a numeric value.');
        }

        $index = (int)$index;

        if ($index < 0) {
            $index += count($this->chain);
        }

        return isset($this->chain[$index])
            ? $this->chain[$index]
            : false;
    }
}
