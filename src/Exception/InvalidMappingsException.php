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

namespace BrightNucleus\Injector\Exception;

use BrightNucleus\Exception\RuntimeException;

/**
 * Class InvalidMappingsException.
 *
 * @since   0.1.0
 *
 * @package BrightNucleus\Injector\Exception
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class InvalidMappingsException extends RuntimeException implements InjectorException
{

}
