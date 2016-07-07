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

namespace BrightNucleus\Injector\Test;

use BrightNucleus\Config\ConfigFactory;
use BrightNucleus\Config\ConfigInterface;
use BrightNucleus\Injector\Injector;

/**
 * Class InjectorTest.
 *
 * Most of these tests are taken as is from the original Auryn injector. We re-run these here to test for regressions.
 *
 * @since   0.1.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class InjectorTest extends \PHPUnit_Framework_TestCase
{

    public function testMakeInstancesThroughConfigAlias()
    {
        $injector = new Injector(ConfigFactory::create([
            'standardAliases' => [
                'BNFoo' => 'BrightNucleus\Injector\Test\NotSharedClass',
            ],
            'sharedAliases'   => [
                'BNBar' => 'BrightNucleus\Injector\Test\SharedClass',
            ],
        ]));
        $objFooA  = $injector->make('BNFoo');
        $objFooB  = $injector->make('BNFoo');
        $objBarA  = $injector->make('BNBar');
        $objBarB  = $injector->make('BNBar');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\NotSharedClass', $objFooA);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\NotSharedClass', $objFooB);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\SharedClass', $objBarA);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\SharedClass', $objBarB);
        $this->assertNotSame($objFooA, $objFooB);
        $this->assertSame($objBarA, $objBarB);
    }

    public function testLoadConfigThroughArgumentAlias()
    {
        $injector = new Injector(ConfigFactory::createFromArray([
            'standardAliases'   => [
                'BrightNucleus\Config\ConfigInterface' => 'BrightNucleus\Config\Config',
                'BNConfigFoo'                          => 'BrightNucleus\Injector\Test\ConfigClass',
            ],
            'argumentProviders' => [
                'config' => [
                    'interface' => ConfigInterface::class,
                    'mappings'  => [
                        'BrightNucleus\Injector\Test\ConfigClass' => function ($interface) {
                            return ConfigFactory::createSubConfig(
                                __DIR__ . '/fixtures/ConfigFile.php',
                                'TestData'
                            );
                        },
                    ],
                ],
            ],
        ]));
        $obj      = $injector->make('BNConfigFoo');
        $this->assertEquals('testValue', $obj->check('randomString'));
        $this->assertEquals('testValue', $obj->check('randomString'));
        $this->assertEquals(42, $obj->check('positiveInteger'));
        $this->assertEquals(-256, $obj->check('negativeInteger'));
        $this->assertEquals(true, $obj->check('positiveBoolean'));
        $this->assertEquals(false, $obj->check('negativeBoolean'));
    }

    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $injector->make('BrightNucleus\Injector\Test\TestNeedsDep')
        );
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertEquals(new TestNoConstructor, $injector->make('BrightNucleus\Injector\Test\TestNoConstructor'));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\DepInterface', 'BrightNucleus\Injector\Test\DepImplementation');
        $this->assertEquals(new DepImplementation, $injector->make('BrightNucleus\Injector\Test\DepInterface'));
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_NEEDS_DEFINITION
     */
    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->make('BrightNucleus\Injector\Test\DepInterface');
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_NEEDS_DEFINITION
     */
    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->make('BrightNucleus\Injector\Test\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\DepInterface', 'BrightNucleus\Injector\Test\DepImplementation');
        $obj = $injector->make('BrightNucleus\Injector\Test\RequiresInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\RequiresInterface', $obj);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined()
    {
        $injector         = new Injector(ConfigFactory::create([]));
        $nullCtorParamObj = $injector->make('BrightNucleus\Injector\Test\ProvTestNoDefinitionNullDefaultClass');
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertEquals(null, $nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Test\RequiresInterface',
            array('dep' => 'BrightNucleus\Injector\Test\DepImplementation'));
        $injector->share('BrightNucleus\Injector\Test\RequiresInterface');
        $injected = $injector->make('BrightNucleus\Injector\Test\RequiresInterface');

        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make('BrightNucleus\Injector\Test\RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    /**
     * @expectedException Auryn\InjectorException
     */
    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Test\TestNeedsDep',
            array('testDep' => 'BrightNucleus\Injector\Test\TestDependency'));
        $injected = $injector->make('BrightNucleus\Injector\Test\TestNeedsDep',
            array('testDep' => 'BrightNucleus\Injector\Test\TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Test\InjectorTestChildClass',
            array(':arg1' => 'First argument', ':arg2' => 'Second argument'));
        $injected = $injector->make('BrightNucleus\Injector\Test\InjectorTestChildClass', array(':arg1' => 'Override'));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\TestDependency');
        $obj = $injector->make('BrightNucleus\Injector\Test\TestDependency');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\TestDependency', $obj);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Test\TestMultiDepsWithCtor',
            array('val1' => 'BrightNucleus\Injector\Test\TestDependency'));
        $this->assertInstanceOf('BrightNucleus\Injector\Test\TestMultiDepsWithCtor', $obj);

        $obj = $injector->make('BrightNucleus\Injector\Test\NoTypehintNoDefaultConstructorClass',
            array('val1' => 'BrightNucleus\Injector\Test\TestDependency')
        );
        $this->assertInstanceOf('BrightNucleus\Injector\Test\NoTypehintNoDefaultConstructorClass', $obj);
        $this->assertEquals(null, $obj->testParam);
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_UNDEFINED_PARAM
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Test\InjectorTestCtorParamWithNoTypehintOrDefault');
        $this->assertNull($obj->val);
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_UNDEFINED_PARAM
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint(
    )
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\TestNoExplicitDefine',
            'BrightNucleus\Injector\Test\InjectorTestCtorParamWithNoTypehintOrDefault');
        $injector->make('BrightNucleus\Injector\Test\InjectorTestCtorParamWithNoTypehintOrDefaultDependent');
    }

    /**
     * @TODO
     * @expectedException Auryn\InjectorException
     */
    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Test\RequiresInterface');
    }

    public function testTypelessDefineForDependency()
    {
        $thumbnailSize = 128;
        $injector      = new Injector(ConfigFactory::create([]));
        $injector->defineParam('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make('BrightNucleus\Injector\Test\RequiresDependencyWithTypelessParameters');
        $this->assertEquals($thumbnailSize, $testClass->getThumbnailSize(),
            'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->defineParam('val', 42);

        $injector->alias('BrightNucleus\Injector\Test\TestNoExplicitDefine',
            'BrightNucleus\Injector\Test\ProviderTestCtorParamWithNoTypehintOrDefault');
        $obj = $injector->make('BrightNucleus\Injector\Test\ProviderTestCtorParamWithNoTypehintOrDefaultDependent');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\ProviderTestCtorParamWithNoTypehintOrDefaultDependent',
            $obj);
    }

    public function testMakeInstanceInjectsRawParametersDirectly()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Test\InjectorTestRawCtorParams', array(
            ':string' => 'string',
            ':obj'    => new \StdClass,
            ':int'    => 42,
            ':array'  => array(),
            ':float'  => 9.3,
            ':bool'   => true,
            ':null'   => null,
        ));

        $obj = $injector->make('BrightNucleus\Injector\Test\InjectorTestRawCtorParams');
        $this->assertInternalType('string', $obj->string);
        $this->assertInstanceOf('StdClass', $obj->obj);
        $this->assertInternalType('int', $obj->int);
        $this->assertInternalType('array', $obj->array);
        $this->assertInternalType('float', $obj->float);
        $this->assertInternalType('bool', $obj->bool);
        $this->assertNull($obj->null);
    }

    /**
     * @TODO
     * @expectedException \Exception
     */
    public function testMakeInstanceThrowsExceptionWhenDelegateDoes()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $callable = $this->getMock(
            'CallableMock',
            array('__invoke')
        );

        $injector->delegate('TestDependency', $callable);

        $callable->expects($this->once())
                 ->method('__invoke')
                 ->will($this->throwException(new \Exception()));

        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Test\SomeClassName');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\SomeClassName', $obj);
    }

    public function testMakeInstanceDelegate()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $callable = $this->getMock(
            'CallableMock',
            array('__invoke')
        );
        $callable->expects($this->once())
                 ->method('__invoke')
                 ->will($this->returnValue(new TestDependency()));

        $injector->delegate('BrightNucleus\Injector\Test\TestDependency', $callable);

        $obj = $injector->make('BrightNucleus\Injector\Test\TestDependency');

        $this->assertInstanceOf('BrightNucleus\Injector\Test\TestDependency', $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('StdClass', 'BrightNucleus\Injector\Test\StringStdClassDelegateMock');
        $obj = $injector->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    /**
     * @expectedException Auryn\ConfigException
     */
    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('StdClass', 'StringDelegateWithNoInvokeMethod');
    }

    /**
     * @expectedException Auryn\ConfigException
     */
    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    /**
     * @expectedException \Auryn\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Test\RequiresInterface');
    }

    public function testDefineAssignsPassedDefinition()
    {
        $injector   = new Injector(ConfigFactory::create([]));
        $definition = array('dep' => 'BrightNucleus\Injector\Test\DepImplementation');
        $injector->define('BrightNucleus\Injector\Test\RequiresInterface', $definition);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\RequiresInterface',
            $injector->make('BrightNucleus\Injector\Test\RequiresInterface'));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance()
    {
        $injector        = new Injector(ConfigFactory::create([]));
        $testShare       = new \StdClass;
        $testShare->test = 42;

        $this->assertInstanceOf('Auryn\Injector', $injector->share($testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $injector->make('stdclass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertInstanceOf('Auryn\Injector', $injector->share('SomeClass'));
    }

    /**
     * @expectedException \Auryn\ConfigException
     */
    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share(42);
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertInstanceOf('Auryn\Injector',
            $injector->alias('DepInterface', 'BrightNucleus\Injector\Test\DepImplementation'));
    }

    public function provideInvalidDelegates()
    {
        return array(
            array(new \StdClass),
            array(42),
            array(true),
        );
    }

    /**
     * @dataProvider provideInvalidDelegates
     * @expectedException Auryn\ConfigException
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate)
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\TestDependency', $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\MadeByDelegate',
            'BrightNucleus\Injector\Test\CallableDelegateClassTest');
        $this->assertInstanceof('BrightNucleus\Injector\Test\MadeByDelegate',
            $injector->make('BrightNucleus\Injector\Test\MadeByDelegate'));
    }

    public function testDelegateInstantiatesCallableClassArray()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\MadeByDelegate',
            array('BrightNucleus\Injector\Test\CallableDelegateClassTest', '__invoke'));
        $this->assertInstanceof('BrightNucleus\Injector\Test\MadeByDelegate',
            $injector->make('BrightNucleus\Injector\Test\MadeByDelegate'));
    }

    public function testUnknownDelegationFunction()
    {
        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->delegate('BrightNucleus\Injector\Test\DelegatableInterface', 'FunctionWhichDoesNotExist');
            $this->fail("Delegation was supposed to fail.");
        } catch (\Auryn\InjectorException $ie) {
            $this->assertContains('FunctionWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    public function testUnknownDelegationMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->delegate('BrightNucleus\Injector\Test\DelegatableInterface',
                array('stdClass', 'methodWhichDoesNotExist'));
            $this->fail("Delegation was supposed to fail.");
        } catch (\Auryn\InjectorException $ie) {
            $this->assertContains('stdClass', $ie->getMessage());
            $this->assertContains('methodWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    /**
     * @dataProvider provideExecutionExpectations
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult)
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
    }

    public function provideExecutionExpectations()
    {
        $return = array();

        // 0 -------------------------------------------------------------------------------------->

        $toInvoke       = array('BrightNucleus\Injector\Test\ExecuteClassNoDeps', 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke       = array(new ExecuteClassNoDeps, 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke       = array('BrightNucleus\Injector\Test\ExecuteClassDeps', 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke       = array(new ExecuteClassDeps(new TestDependency), 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke       = array('BrightNucleus\Injector\Test\ExecuteClassDepsWithMethodDeps', 'execute');
        $args           = array(':arg' => 9382);
        $expectedResult = 9382;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke       = array('BrightNucleus\Injector\Test\ExecuteClassStaticMethod', 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke       = array(new ExecuteClassStaticMethod, 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\ExecuteClassStaticMethod::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke       = array('BrightNucleus\Injector\Test\ExecuteClassRelativeStaticMethod', 'parent::execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\testExecuteFunction';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 10 ------------------------------------------------------------------------------------->

        $toInvoke       = function () { return 42; };
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 11 ------------------------------------------------------------------------------------->

        $toInvoke       = new ExecuteClassInvokable;
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 12 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\ExecuteClassInvokable';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\ExecuteClassNoDeps::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\ExecuteClassDeps::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\ExecuteClassStaticMethod::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\ExecuteClassRelativeStaticMethod::parent::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Test\testExecuteFunctionWithArg';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 18 ------------------------------------------------------------------------------------->

        $toInvoke       = function () {
            return 42;
        };
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        if (PHP_VERSION_ID > 50400) {
            // 19 ------------------------------------------------------------------------------------->

            $object         = new \BrightNucleus\Injector\Test\ReturnsCallable('new value');
            $args           = array();
            $toInvoke       = $object->getCallable();
            $expectedResult = 'new value';
            $return[]       = array($toInvoke, $args, $expectedResult);
        }

        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    public function testStaticStringInvokableWithArgument()
    {
        $injector  = new \Auryn\Injector;
        $invokable = $injector->buildExecutable('BrightNucleus\Injector\Test\ClassWithStaticMethodThatTakesArg::doSomething');
        $this->assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\DelegatableInterface',
            'BrightNucleus\Injector\Test\ImplementsInterfaceFactory');
        $requiresDelegatedInterface = $injector->make('BrightNucleus\Injector\Test\RequiresDelegatedInterface');
        $requiresDelegatedInterface->foo();
        $this->assertTrue(true);
    }

    /**
     * @expectedException Auryn\InjectorException
     */
    public function testMissingAlias()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $testClass = $injector->make('BrightNucleus\Injector\Test\TestMissingDependency');
    }

    public function testAliasingConcreteClasses()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\ConcreteClass1', 'BrightNucleus\Injector\Test\ConcreteClass2');
        $obj = $injector->make('BrightNucleus\Injector\Test\ConcreteClass1');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\ConcreteClass2', $obj);
    }

    public function testSharedByAliasedInterfaceName()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\SharedAliasedInterface',
            'BrightNucleus\Injector\Test\SharedClass');
        $injector->share('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $class  = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $class2 = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\SharedAliasedInterface',
            'BrightNucleus\Injector\Test\SharedClass');
        $injector->alias('BrightNucleus\Injector\Test\SharedAliasedInterface',
            'BrightNucleus\Injector\Test\NotSharedClass');
        $injector->share('BrightNucleus\Injector\Test\SharedClass');
        $class  = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $class2 = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');

        $this->assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $injector->alias('BrightNucleus\Injector\Test\SharedAliasedInterface',
            'BrightNucleus\Injector\Test\SharedClass');
        $class  = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $class2 = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\SharedAliasedInterface',
            'BrightNucleus\Injector\Test\SharedClass');
        $injector->share('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $sharedClass = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $childClass  = $injector->make('BrightNucleus\Injector\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\SharedAliasedInterface',
            'BrightNucleus\Injector\Test\SharedClass');
        $sharedClass = $injector->make('BrightNucleus\Injector\Test\SharedAliasedInterface');
        $injector->share($sharedClass);
        $childClass = $injector->make('BrightNucleus\Injector\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('StdClass');
        $stdClass1 = $injector->make('StdClass');
        $injector->share('StdClass');
        $stdClass2 = $injector->make('StdClass');
        $this->assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make('BrightNucleus\Injector\Test\TestNeedsDepWithProtCons');

        $this->assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\ClassInnerB');
        $innerDep = $injector->make('BrightNucleus\Injector\Test\ClassInnerB');
        $inner    = $injector->make('BrightNucleus\Injector\Test\ClassInnerA');
        $this->assertSame($innerDep, $inner->dep);
        $outer = $injector->make('BrightNucleus\Injector\Test\ClassOuter');
        $this->assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Test\ClassOuter');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\ClassOuter', $obj);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\ClassInnerA', $obj->dep);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\ClassInnerB', $obj->dep->dep);
    }

    public function provideCyclicDependencies()
    {
        return array(
            'BrightNucleus\Injector\Test\RecursiveClassA' => array('BrightNucleus\Injector\Test\RecursiveClassA'),
            'BrightNucleus\Injector\Test\RecursiveClassB' => array('BrightNucleus\Injector\Test\RecursiveClassB'),
            'BrightNucleus\Injector\Test\RecursiveClassC' => array('BrightNucleus\Injector\Test\RecursiveClassC'),
            'BrightNucleus\Injector\Test\RecursiveClass1' => array('BrightNucleus\Injector\Test\RecursiveClass1'),
            'BrightNucleus\Injector\Test\RecursiveClass2' => array('BrightNucleus\Injector\Test\RecursiveClass2'),
            'BrightNucleus\Injector\Test\DependsOnCyclic' => array('BrightNucleus\Injector\Test\DependsOnCyclic'),
        );
    }

    /**
     * @dataProvider provideCyclicDependencies
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_CYCLIC_DEPENDENCY
     */
    public function testCyclicDependencies($class)
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $class    = $injector->make('BrightNucleus\Injector\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertNull($class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias(
            'BrightNucleus\Injector\Test\DelegatableInterface',
            'BrightNucleus\Injector\Test\ImplementsInterface'
        );
        $class = $injector->make('BrightNucleus\Injector\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\ImplementsInterface', $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\DelegatableInterface',
            'BrightNucleus\Injector\Test\ImplementsInterfaceFactory');
        $class = $injector->make('BrightNucleus\Injector\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\ImplementsInterface', $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare()
    {
        $injector = new Injector(ConfigFactory::create([]));
        //Instance is not shared, null default is used for dependency
        $instance = $injector->make('BrightNucleus\Injector\Test\ConcreteDependencyWithDefaultValue');
        $this->assertNull($instance->dependency);

        //Instance is explicitly shared, $instance is used for dependency
        $instance = new \StdClass();
        $injector->share($instance);
        $instance = $injector->make('BrightNucleus\Injector\Test\ConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('StdClass', $instance->dependency);
    }

    /**
     * @expectedException \Auryn\ConfigException
     * @expectedExceptionCode \Auryn\Injector::E_ALIASED_CANNOT_SHARE
     */
    public function testShareAfterAliasException()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $testClass = new \StdClass();
        $injector->alias('StdClass', 'BrightNucleus\Injector\Test\SomeOtherClass');
        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $testClass = new DepImplementation();
        $injector->alias('BrightNucleus\Injector\Test\DepInterface', 'BrightNucleus\Injector\Test\DepImplementation');
        $injector->share($testClass);
        $obj = $injector->make('BrightNucleus\Injector\Test\DepInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\DepImplementation', $obj);
    }

    public function testAliasAfterShareByStringAllowed()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\DepInterface');
        $injector->alias('BrightNucleus\Injector\Test\DepInterface', 'BrightNucleus\Injector\Test\DepImplementation');
        $obj  = $injector->make('BrightNucleus\Injector\Test\DepInterface');
        $obj2 = $injector->make('BrightNucleus\Injector\Test\DepInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\DepImplementation');
        $injector->alias('BrightNucleus\Injector\Test\DepInterface', 'BrightNucleus\Injector\Test\DepImplementation');
        $obj  = $injector->make('BrightNucleus\Injector\Test\DepInterface');
        $obj2 = $injector->make('BrightNucleus\Injector\Test\DepInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    /**
     * @expectedException \Auryn\ConfigException
     * @expectedExceptionCode \Auryn\Injector::E_SHARED_CANNOT_ALIAS
     */
    public function testAliasAfterShareException()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $testClass = new \StdClass();
        $injector->share($testClass);
        $injector->alias('StdClass', 'BrightNucleus\Injector\Test\SomeOtherClass');
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR
     */
    public function testAppropriateExceptionThrownOnNonPublicConstructor()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->make('BrightNucleus\Injector\Test\HasNonPublicConstructor');
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR
     */
    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->make('BrightNucleus\Injector\Test\HasNonPublicConstructorWithArgs');
    }

    public function testMakeExecutableFailsOnNonExistentFunction()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->setExpectedException(
            'Auryn\InjectionException',
            'nonExistentFunction',
            \Auryn\Injector::E_INVOKABLE
        );
        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $object   = new \StdClass();
        $this->setExpectedException(
            'Auryn\InjectionException',
            "[object(stdClass), 'nonExistentMethod']",
            \Auryn\Injector::E_INVOKABLE
        );
        $injector->buildExecutable(array($object, 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->setExpectedException(
            'Auryn\InjectionException',
            "StdClass::nonExistentMethod",
            \Auryn\Injector::E_INVOKABLE
        );
        $injector->buildExecutable(array('StdClass', 'nonExistentMethod'));
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_INVOKABLE
     */
    public function testMakeExecutableFailsOnClassWithoutInvoke()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $object   = new \StdClass();
        $injector->buildExecutable($object);
    }

    /**
     * @expectedException \Auryn\ConfigException
     * @expectedExceptionCode \Auryn\Injector::E_NON_EMPTY_STRING_ALIAS
     */
    public function testBadAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\DepInterface');
        $injector->alias('BrightNucleus\Injector\Test\DepInterface', '');
    }

    public function testShareNewAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\DepImplementation');
        $injector->alias('BrightNucleus\Injector\Test\DepInterface', 'BrightNucleus\Injector\Test\DepImplementation');
        $this->assertTrue(true);
    }

    public function testDefineWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Test\SimpleNoTypehintClass', array(':arg' => 'tested'));
        $testClass = $injector->make('BrightNucleus\Injector\Test\SimpleNoTypehintClass');
        $this->assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('\StdClass');
        $classA         = $injector->make('StdClass');
        $classA->tested = false;
        $classB         = $injector->make('\StdClass');
        $classB->tested = true;

        $this->assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->prepare('\StdClass', function ($obj, $injector) {
            $obj->testval = 42;
        });
        $obj = $injector->make('StdClass');

        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->prepare('BrightNucleus\Injector\Test\SomeInterface', function ($obj, $injector) {
            $obj->testProp = 42;
        });
        $obj = $injector->make('BrightNucleus\Injector\Test\PreparesImplementationTest');

        $this->assertSame(42, $obj->testProp);
    }

    /**
     * Test that custom definitions are not passed through to dependencies.
     * Surprising things would happen if this did occur.
     *
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_UNDEFINED_PARAM
     */
    public function testCustomDefinitionNotPassedThrough()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\DependencyWithDefinedParam');
        $injector->make('BrightNucleus\Injector\Test\RequiresDependencyWithDefinedParam', array(':foo' => 5));
    }

    public function testDelegationFunction()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\TestDelegationSimple',
            'BrightNucleus\Injector\Test\createTestDelegationSimple');
        $obj = $injector->make('BrightNucleus\Injector\Test\TestDelegationSimple');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\TestDelegationSimple', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate(
            'BrightNucleus\Injector\Test\TestDelegationDependency',
            'BrightNucleus\Injector\Test\createTestDelegationDependency'
        );
        $obj = $injector->make('BrightNucleus\Injector\Test\TestDelegationDependency');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\TestDelegationDependency', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\BaseExecutableClass',
            'BrightNucleus\Injector\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('BrightNucleus\Injector\Test\BaseExecutableClass', 'foo'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Test\BaseExecutableClass',
            'BrightNucleus\Injector\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('BrightNucleus\Injector\Test\BaseExecutableClass', 'bar'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside
     * of a class.ph
     *
     * @throws \Auryn\ConfigException
     */
    public function testDelegateClosure()
    {
        $delegateClosure = \BrightNucleus\Injector\Test\getDelegateClosureInGlobalScope();
        $injector        = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\DelegateClosureInGlobalScope', $delegateClosure);
        $obj = $injector->make('BrightNucleus\Injector\Test\DelegateClosureInGlobalScope');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\DelegateClosureInGlobalScope', $obj);
    }

    public function testCloningWithServiceLocator()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share($injector);
        $instance    = $injector->make('BrightNucleus\Injector\Test\CloneTest');
        $newInjector = $instance->injector;
        $newInstance = $newInjector->make('BrightNucleus\Injector\Test\CloneTest');
        $this->assertInstanceOf('BrightNucleus\Injector\Test\CloneTest', $instance);
        $this->assertInstanceOf('BrightNucleus\Injector\Test\CloneTest', $newInstance);
    }

    public function testAbstractExecute()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $fn = function () {
            return new \BrightNucleus\Injector\Test\ConcreteExexcuteTest();
        };

        $injector->delegate('BrightNucleus\Injector\Test\AbstractExecuteTest', $fn);
        $result = $injector->execute(array('BrightNucleus\Injector\Test\AbstractExecuteTest', 'process'));

        $this->assertEquals('Concrete', $result);
    }

    public function testDebugMake()
    {
        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->make('BrightNucleus\Injector\Test\DependencyChainTest');
        } catch (\Auryn\InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            $this->assertCount(2, $chain);

            $this->assertEquals('brightnucleus\injector\test\dependencychaintest', $chain[0]);
            $this->assertEquals('brightnucleus\injector\test\depinterface', $chain[1]);
        }
    }

    public function testInspectShares()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Test\SomeClassName');

        $inspection = $injector->inspect('BrightNucleus\Injector\Test\SomeClassName', Injector::I_SHARES);
        $this->assertArrayHasKey('brightnucleus\injector\test\someclassname', $inspection[Injector::I_SHARES]);
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_MAKING_FAILED
     */
    public function testDelegationDoesntMakeObject()
    {
        $delegate = function () {
            return null;
        };
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\SomeClassName', $delegate);
        $injector->make('BrightNucleus\Injector\Test\SomeClassName');
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_MAKING_FAILED
     */
    public function testDelegationDoesntMakeObjectMakesString()
    {
        $delegate = function () {
            return 'ThisIsNotAClass';
        };
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Test\SomeClassName', $delegate);
        $injector->make('BrightNucleus\Injector\Test\SomeClassName');
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("BrightNucleus\Injector\Test\SomeInterface", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("BrightNucleus\Injector\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("BrightNucleus\Injector\Test\SomeImplementation", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("BrightNucleus\Injector\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testChildWithoutConstructorWorks()
    {

        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->define('BrightNucleus\Injector\Test\ParentWithConstructor', array(':foo' => 'parent'));
            $injector->define('BrightNucleus\Injector\Test\ChildWithoutConstructor', array(':foo' => 'child'));

            $injector->share('BrightNucleus\Injector\Test\ParentWithConstructor');
            $injector->share('BrightNucleus\Injector\Test\ChildWithoutConstructor');

            $child = $injector->make('BrightNucleus\Injector\Test\ChildWithoutConstructor');
            $this->assertEquals('child', $child->foo);

            $parent = $injector->make('BrightNucleus\Injector\Test\ParentWithConstructor');
            $this->assertEquals('parent', $parent->foo);
        } catch (\Auryn\InjectionException $ie) {
            echo $ie->getMessage();
            $this->fail("Auryn failed to locate the ");
        }
    }

    /**
     * @expectedException \Auryn\InjectionException
     * @expectedExceptionCode \Auryn\Injector::E_UNDEFINED_PARAM
     */
    public function testChildWithoutConstructorMissingParam()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Test\ParentWithConstructor', array(':foo' => 'parent'));
        $injector->make('BrightNucleus\Injector\Test\ChildWithoutConstructor');
    }
}
