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

namespace BrightNucleus\Injector\Tests;

$testData = [

    'randomString'    => 'testValue',
    'positiveInteger' => 42,
    'negativeInteger' => -256,
    'positiveBoolean' => true,
    'negativeBoolean' => false,

];

return ['ConfigClass' => $testData];
