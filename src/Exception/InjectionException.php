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
use Exception;

/**
 * Class InjectionException.
 *
 * @since   0.3.0
 *
 * @package BrightNucleus\Injector\Exception
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class InjectionException extends RuntimeException implements InjectorException
{

    public $dependencyChain;

    public function __construct(array $inProgressMakes, $message = "", $code = 0, \Exception $previous = null)
    {
        $this->dependencyChain = array_flip($inProgressMakes);
        ksort($this->dependencyChain);

        parent::__construct($message, $code, $previous);
    }

    /**
     * Add a human readable version of the invalid callable to the standard 'invalid invokable' message.
     */
    public static function fromInvalidCallable(
        array $inProgressMakes,
        $callableOrMethodStr,
        Exception $previous = null
    ) {
        $callableString = null;

        if (is_string($callableOrMethodStr)) {
            $callableString .= $callableOrMethodStr;
        } elseif (is_array($callableOrMethodStr) &&
                   array_key_exists(0, $callableOrMethodStr) &&
                   array_key_exists(0, $callableOrMethodStr)
        ) {
            if (is_string($callableOrMethodStr[0]) && is_string($callableOrMethodStr[1])) {
                $callableString .= $callableOrMethodStr[0] . '::' . $callableOrMethodStr[1];
            } elseif (is_object($callableOrMethodStr[0]) && is_string($callableOrMethodStr[1])) {
                $callableString .= sprintf(
                    "[object(%s), '%s']",
                    get_class($callableOrMethodStr[0]),
                    $callableOrMethodStr[1]
                );
            }
        }

        if ($callableString) {
            // Prevent accidental usage of long strings from filling logs.
            $callableString = substr($callableString, 0, 250);
            $message        = sprintf(
                "%s. Invalid callable was '%s'",
                InjectorException::M_INVOKABLE,
                $callableString
            );
        } else {
            $message = InjectorException::M_INVOKABLE;
        }

        return new self($inProgressMakes, $message, InjectorException::E_INVOKABLE, $previous);
    }

    /**
     * Returns the hierarchy of dependencies that were being created when
     * the exception occurred.
     *
     * @return array
     */
    public function getDependencyChain()
    {
        return $this->dependencyChain;
    }
}
