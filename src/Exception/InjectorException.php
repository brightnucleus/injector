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

use BrightNucleus\Exception\ExceptionInterface;

/**
 * Interface InjectorException.
 *
 * @since   0.1.0
 *
 * @package BrightNucleus\Injector\Exception
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
interface InjectorException extends ExceptionInterface
{

    const E_NON_EMPTY_STRING_ALIAS = 1;
    const M_NON_EMPTY_STRING_ALIAS = 'Invalid alias: non-empty string required at arguments 1 and 2';
    const E_SHARED_CANNOT_ALIAS    = 2;
    const M_SHARED_CANNOT_ALIAS    = 'Cannot alias class %s to %s because it is currently shared';
    const E_SHARE_ARGUMENT         = 3;
    const M_SHARE_ARGUMENT         = '%s::share() requires a string class name or object instance at Argument 1;'
        . ' %s specified';
    const E_ALIASED_CANNOT_SHARE   = 4;
    const M_ALIASED_CANNOT_SHARE   = 'Cannot share class %s because it is currently aliased to %s';
    const E_INVOKABLE              = 5;
    const M_INVOKABLE              = 'Invalid invokable: callable or provisional string required';
    const E_NON_PUBLIC_CONSTRUCTOR = 6;
    const M_NON_PUBLIC_CONSTRUCTOR = 'Cannot instantiate public/public constructor in class %s';
    const E_NEEDS_DEFINITION       = 7;
    const M_NEEDS_DEFINITION       = 'Injection definition required for %s %s';
    const E_MAKE_FAILURE           = 8;
    const M_MAKE_FAILURE           = 'Could not make %s: %s';
    const E_UNDEFINED_PARAM        = 9;
    const M_UNDEFINED_PARAM        = 'No definition available to provision typeless parameter $%s at position %d in'
        . ' %s(). Injection Chain: %s';
    const E_DELEGATE_ARGUMENT      = 10;
    const M_DELEGATE_ARGUMENT      = '%s::delegate expects a valid callable or executable class::method string at'
        . ' Argument 2%s';
    const E_CYCLIC_DEPENDENCY      = 11;
    const M_CYCLIC_DEPENDENCY      = 'Detected a cyclic dependency while provisioning %s';
    const E_MAKING_FAILED          = 12;
    const M_MAKING_FAILED          = 'Making %s did not result in an object, instead result is of type \'%s\'';
}
