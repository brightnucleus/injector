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

use BrightNucleus\Config\ConfigFactory;
use BrightNucleus\Config\ConfigInterface;
use BrightNucleus\Injector\Exception\InjectionException;
use BrightNucleus\Injector\InjectionChain;
use BrightNucleus\Injector\Injector;
use BrightNucleus\Injector\Exception\InjectorException;
use BrightNucleus\Injector\Tests\TestCase;
use stdClass;

/**
 * Class InjectorTest.
 *
 * Most of these tests are taken from the original Auryn injector and modified to work with the refactoring.
 *
 * @since   0.1.0
 *
 * @package BrightNucleus\Injector
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class InjectorTest extends TestCase
{

    public function testMakeInstancesThroughConfigAlias()
    {
        $injector = new Injector(ConfigFactory::create([
            'standardAliases' => [
                'BNFoo' => 'BrightNucleus\Injector\Tests\NotSharedClass',
            ],
            'sharedAliases'   => [
                'BNBar' => 'BrightNucleus\Injector\Tests\SharedClass',
            ],
        ]));
        $objFooA  = $injector->make('BNFoo');
        $objFooB  = $injector->make('BNFoo');
        $objBarA  = $injector->make('BNBar');
        $objBarB  = $injector->make('BNBar');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\NotSharedClass',
            $objFooA);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\NotSharedClass',
            $objFooB);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\SharedClass',
            $objBarA);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\SharedClass',
            $objBarB);
        $this->assertNotSame($objFooA, $objFooB);
        $this->assertSame($objBarA, $objBarB);
    }

    public function testLoadConfigThroughArgumentAlias()
    {
        $injector = new Injector(ConfigFactory::createFromArray([
            'standardAliases'   => [
                'BrightNucleus\Config\ConfigInterface' => 'BrightNucleus\Config\Config',
                'BNConfigFoo'                          => 'BrightNucleus\Injector\Tests\ConfigClass',
            ],
            'argumentProviders' => [
                'config' => [
                    'interface' => ConfigInterface::class,
                    'mappings'  => [
                        'BrightNucleus\Injector\Tests\ConfigClass' => function ($alias, $interface) {
                            return ConfigFactory::createSubConfig(
                                __DIR__ . '/fixtures/ConfigFile.php',
                                substr(strrchr($alias, '\\'), 1)
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

    public function testArgumentDefinitionsThroughConfig()
    {
        $injector = new Injector(ConfigFactory::createFromArray([
            'argumentDefinitions' => [
                'BrightNucleus\Injector\Tests\DependencyWithDefinedParam' => [
                    'foo' => 42,
                ],
            ],
        ]));
        $obj      = $injector->make('BrightNucleus\Injector\Tests\DependencyWithDefinedParam');
        $this->assertEquals(42, $obj->foo);
    }

    public function testDelegationsThroughConfig()
    {
        $injector = new Injector(ConfigFactory::createFromArray([
            'delegations' => [
                'stdClass' => function () {
                    return new SomeClassName();
                },
            ],
        ]));
        $obj      = $injector->make('stdClass');
        $this->assertInstanceOf(SomeClassName::class, $obj);
    }

    public function testPreparationsThroughConfig()
    {
        $injector = new Injector(ConfigFactory::createFromArray([
            'preparations' => [
                'stdClass'                                  => function ($obj, $injector) {
                    $obj->testval = 42;
                },
                'BrightNucleus\Injector\Tests\SomeInterface' => function ($obj, $injector) {
                    $obj->testProp = 42;
                },
            ],
        ]));
        $obj1     = $injector->make('stdClass');
        $this->assertSame(42, $obj1->testval);
        $obj2 = $injector->make('BrightNucleus\Injector\Tests\PreparesImplementationTest');
        $this->assertSame(42, $obj2->testProp);
    }

    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $injector->make('BrightNucleus\Injector\Tests\TestNeedsDep')
        );
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertEquals(new TestNoConstructor,
            $injector->make('BrightNucleus\Injector\Tests\TestNoConstructor'));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\DepInterface',
            'BrightNucleus\Injector\Tests\DepImplementation');
        $this->assertEquals(new DepImplementation,
            $injector->make('BrightNucleus\Injector\Tests\DepInterface'));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_NEEDS_DEFINITION);
        $injector->make('BrightNucleus\Injector\Tests\DepInterface');
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_NEEDS_DEFINITION);
        $injector->make('BrightNucleus\Injector\Tests\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\DepInterface',
            'BrightNucleus\Injector\Tests\DepImplementation');
        $obj = $injector->make('BrightNucleus\Injector\Tests\RequiresInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\RequiresInterface',
            $obj);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined()
    {
        $injector         = new Injector(ConfigFactory::create([]));
        $nullCtorParamObj = $injector->make('BrightNucleus\Injector\Tests\ProvTestNoDefinitionNullDefaultClass');
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertEquals(null, $nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Tests\RequiresInterface',
            array('dep' => 'BrightNucleus\Injector\Tests\DepImplementation'));
        $injector->share('BrightNucleus\Injector\Tests\RequiresInterface');
        $injected = $injector->make('BrightNucleus\Injector\Tests\RequiresInterface');

        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make('BrightNucleus\Injector\Tests\RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_MAKE_FAILURE);
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Tests\TestNeedsDep',
            array('testDep' => 'BrightNucleus\Injector\Tests\TestDependency'));
        $injected = $injector->make('BrightNucleus\Injector\Tests\TestNeedsDep',
            array('testDep' => 'BrightNucleus\Injector\Tests\TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Tests\InjectorTestChildClass',
            array(
                ':arg1' => 'First argument',
                ':arg2' => 'Second argument',
            ));
        $injected = $injector->make('BrightNucleus\Injector\Tests\InjectorTestChildClass',
            array(':arg1' => 'Override'));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\TestDependency');
        $obj = $injector->make('BrightNucleus\Injector\Tests\TestDependency');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\TestDependency', $obj);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Tests\TestMultiDepsWithCtor',
            array('val1' => 'BrightNucleus\Injector\Tests\TestDependency'));
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\TestMultiDepsWithCtor', $obj);

        $obj = $injector->make('BrightNucleus\Injector\Tests\NoTypehintNoDefaultConstructorClass',
            array('val1' => 'BrightNucleus\Injector\Tests\TestDependency')
        );
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\NoTypehintNoDefaultConstructorClass', $obj);
        $this->assertEquals(null, $obj->testParam);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_UNDEFINED_PARAM);
        $injector->make('BrightNucleus\Injector\Tests\InjectorTestCtorParamWithNoTypehintOrDefault');
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint(
    )
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\TestNoExplicitDefine',
            'BrightNucleus\Injector\Tests\InjectorTestCtorParamWithNoTypehintOrDefault');
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_UNDEFINED_PARAM);
        $injector->make('BrightNucleus\Injector\Tests\InjectorTestCtorParamWithNoTypehintOrDefaultDependent');
    }

    /**
     * @TODO
     */
    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectorException');
        $this->expectExceptionCode(InjectorException::E_NEEDS_DEFINITION);
        $obj      = $injector->make('BrightNucleus\Injector\Tests\RequiresInterface');
    }

    public function testTypelessDefineForDependency()
    {
        $thumbnailSize = 128;
        $injector      = new Injector(ConfigFactory::create([]));
        $injector->defineParam('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make('BrightNucleus\Injector\Tests\RequiresDependencyWithTypelessParameters');
        $this->assertEquals($thumbnailSize, $testClass->getThumbnailSize(),
            'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->defineParam('val', 42);

        $injector->alias('BrightNucleus\Injector\Tests\TestNoExplicitDefine',
            'BrightNucleus\Injector\Tests\ProviderTestCtorParamWithNoTypehintOrDefault');
        $obj = $injector->make('BrightNucleus\Injector\Tests\ProviderTestCtorParamWithNoTypehintOrDefaultDependent');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\ProviderTestCtorParamWithNoTypehintOrDefaultDependent',
            $obj);
    }

    public function testMakeInstanceInjectsRawParametersDirectly()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Tests\InjectorTestRawCtorParams',
            array(
                ':string' => 'string',
                ':obj'    => new stdClass,
                ':int'    => 42,
                ':array'  => array(),
                ':float'  => 9.3,
                ':bool'   => true,
                ':null'   => null,
            ));

        $obj = $injector->make('BrightNucleus\Injector\Tests\InjectorTestRawCtorParams');
        $this->assertIsString($obj->string);
        $this->assertInstanceOf('stdClass', $obj->obj);
        $this->assertIsInt($obj->int);
        $this->assertIsArray($obj->array);
        $this->assertIsFloat($obj->float);
        $this->assertIsBool($obj->bool);
        $this->assertNull($obj->null);
    }

    /**
     * @TODO
     * @expectedException \Exception
     */
    public function testMakeInstanceThrowsExceptionWhenDelegateDoes()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $callable = $this->getMockBuilder(CallableMock::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $injector->delegate('TestDependency', $callable);

        $callable->expects($this->once())
                 ->method('__invoke')
                 ->will($this->throwException(new \Exception()));

        $this->expectException(\Exception::class);
        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Tests\SomeClassName');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\SomeClassName', $obj);
    }

    public function testMakeInstanceDelegate()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $callable = $this->getMockBuilder(CallableMock::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $callable->expects($this->once())
                 ->method('__invoke')
                 ->will($this->returnValue(new TestDependency()));

        $injector->delegate('BrightNucleus\Injector\Tests\TestDependency', $callable);

        $obj = $injector->make('BrightNucleus\Injector\Tests\TestDependency');

        $this->assertInstanceOf('BrightNucleus\Injector\Tests\TestDependency', $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('stdClass',
            'BrightNucleus\Injector\Tests\StringstdClassDelegateMock');
        $obj = $injector->make('stdClass');
        $this->assertEquals(42, $obj->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\ConfigException');
        $this->expectExceptionCode(InjectorException::E_DELEGATE_ARGUMENT);
        $injector->delegate('stdClass', 'StringDelegateWithNoInvokeMethod');
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\ConfigException');
        $this->expectExceptionCode(InjectorException::E_DELEGATE_ARGUMENT);
        $injector->delegate('stdClass',
            'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_NEEDS_DEFINITION);
        $obj      = $injector->make('BrightNucleus\Injector\Tests\RequiresInterface');
    }

    public function testDefineAssignsPassedDefinition()
    {
        $injector   = new Injector(ConfigFactory::create([]));
        $definition = array('dep' => 'BrightNucleus\Injector\Tests\DepImplementation');
        $injector->define('BrightNucleus\Injector\Tests\RequiresInterface',
            $definition);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\RequiresInterface',
            $injector->make('BrightNucleus\Injector\Tests\RequiresInterface'));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance()
    {
        $injector        = new Injector(ConfigFactory::create([]));
        $testShare       = new stdClass;
        $testShare->test = 42;

        $this->assertInstanceOf('BrightNucleus\Injector\Injector',
            $injector->share($testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $injector->make('stdClass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertInstanceOf('BrightNucleus\Injector\Injector',
            $injector->share('SomeClass'));
    }

    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\ConfigException');
        $this->expectExceptionCode(InjectorException::E_SHARE_ARGUMENT);
        $injector->share(42);
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->assertInstanceOf('BrightNucleus\Injector\Injector',
            $injector->alias('DepInterface',
                'BrightNucleus\Injector\Tests\DepImplementation'));
    }

    public function provideInvalidDelegates()
    {
        return array(
            array(new stdClass),
            array(42),
            array(true),
        );
    }

    /**
     * @dataProvider provideInvalidDelegates
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate)
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\ConfigException');
        $this->expectExceptionCode(InjectorException::E_DELEGATE_ARGUMENT);
        $injector->delegate('BrightNucleus\Injector\Tests\TestDependency', $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\MadeByDelegate',
            'BrightNucleus\Injector\Tests\CallableDelegateClassTest');
        $this->assertInstanceof('BrightNucleus\Injector\Tests\MadeByDelegate',
            $injector->make('BrightNucleus\Injector\Tests\MadeByDelegate'));
    }

    public function testDelegateInstantiatesCallableClassArray()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\MadeByDelegate',
            array(
                'BrightNucleus\Injector\Tests\CallableDelegateClassTest',
                '__invoke',
            ));
        $this->assertInstanceof('BrightNucleus\Injector\Tests\MadeByDelegate',
            $injector->make('BrightNucleus\Injector\Tests\MadeByDelegate'));
    }

    public function testUnknownDelegationFunction()
    {
        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->delegate('BrightNucleus\Injector\Tests\DelegatableInterface', 'FunctionWhichDoesNotExist');
            $this->fail("Delegation was supposed to fail.");
        } catch (InjectorException $ie) {
            $this->assertStringContainsString('FunctionWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(InjectorException::E_DELEGATE_ARGUMENT,
                $ie->getCode());
        }
    }

    public function testUnknownDelegationMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->delegate('BrightNucleus\Injector\Tests\DelegatableInterface',
                array('stdClass', 'methodWhichDoesNotExist'));
            $this->fail("Delegation was supposed to fail.");
        } catch (InjectorException $ie) {
            $this->assertStringContainsString('stdClass', $ie->getMessage());
            $this->assertStringContainsString('methodWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(InjectorException::E_DELEGATE_ARGUMENT, $ie->getCode());
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

        $toInvoke       = array(
            'BrightNucleus\Injector\Tests\ExecuteClassNoDeps',
            'execute',
        );
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke       = array(new ExecuteClassNoDeps, 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke       = array(
            'BrightNucleus\Injector\Tests\ExecuteClassDeps',
            'execute',
        );
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke       = array(
            new ExecuteClassDeps(new TestDependency),
            'execute',
        );
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke       = array(
            'BrightNucleus\Injector\Tests\ExecuteClassDepsWithMethodDeps',
            'execute',
        );
        $args           = array(':arg' => 9382);
        $expectedResult = 9382;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke       = array(
            'BrightNucleus\Injector\Tests\ExecuteClassStaticMethod',
            'execute',
        );
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke       = array(new ExecuteClassStaticMethod, 'execute');
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Tests\ExecuteClassStaticMethod::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke       = array(
            'BrightNucleus\Injector\Tests\ExecuteClassRelativeStaticMethod',
            'parent::execute',
        );
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Tests\testExecuteFunction';
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

        $toInvoke       = 'BrightNucleus\Injector\Tests\ExecuteClassInvokable';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Tests\ExecuteClassNoDeps::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Tests\ExecuteClassDeps::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Tests\ExecuteClassStaticMethod::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Tests\ExecuteClassRelativeStaticMethod::parent::execute';
        $args           = array();
        $expectedResult = 42;
        $return[]       = array($toInvoke, $args, $expectedResult);

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke       = 'BrightNucleus\Injector\Tests\testExecuteFunctionWithArg';
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

            $object         = new ReturnsCallable('new value');
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
        $injector  = new Injector(ConfigFactory::create([]));
        $invokable = $injector->buildExecutable('BrightNucleus\Injector\Tests\ClassWithStaticMethodThatTakesArg::doSomething');
        $this->assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\DelegatableInterface',
            'BrightNucleus\Injector\Tests\ImplementsInterfaceFactory');
        $requiresDelegatedInterface = $injector->make('BrightNucleus\Injector\Tests\RequiresDelegatedInterface');
        $requiresDelegatedInterface->foo();
        $this->assertTrue(true);
    }

    public function testMissingAlias()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectorException');
        $this->expectExceptionCode(InjectorException::E_MAKE_FAILURE);
        $injector->make('BrightNucleus\Injector\Tests\TestMissingDependency');
    }

    public function testAliasingConcreteClasses()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\ConcreteClass1', 'BrightNucleus\Injector\Tests\ConcreteClass2');
        $obj = $injector->make('BrightNucleus\Injector\Tests\ConcreteClass1');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\ConcreteClass2', $obj);
    }

    public function testSharedByAliasedInterfaceName()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\SharedAliasedInterface',
            'BrightNucleus\Injector\Tests\SharedClass');
        $injector->share('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $class  = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $class2 = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\SharedAliasedInterface',
            'BrightNucleus\Injector\Tests\SharedClass');
        $injector->alias('BrightNucleus\Injector\Tests\SharedAliasedInterface',
            'BrightNucleus\Injector\Tests\NotSharedClass');
        $injector->share('BrightNucleus\Injector\Tests\SharedClass');
        $class  = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $class2 = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');

        $this->assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $injector->alias('BrightNucleus\Injector\Tests\SharedAliasedInterface',
            'BrightNucleus\Injector\Tests\SharedClass');
        $class  = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $class2 = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\SharedAliasedInterface',
            'BrightNucleus\Injector\Tests\SharedClass');
        $injector->share('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $sharedClass = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $childClass  = $injector->make('BrightNucleus\Injector\Tests\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\SharedAliasedInterface',
            'BrightNucleus\Injector\Tests\SharedClass');
        $sharedClass = $injector->make('BrightNucleus\Injector\Tests\SharedAliasedInterface');
        $injector->share($sharedClass);
        $childClass = $injector->make('BrightNucleus\Injector\Tests\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('stdClass');
        $stdClass1 = $injector->make('stdClass');
        $injector->share('stdClass');
        $stdClass2 = $injector->make('stdClass');
        $this->assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make('BrightNucleus\Injector\Tests\TestNeedsDepWithProtCons');

        $this->assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\ClassInnerB');
        $innerDep = $injector->make('BrightNucleus\Injector\Tests\ClassInnerB');
        $inner    = $injector->make('BrightNucleus\Injector\Tests\ClassInnerA');
        $this->assertSame($innerDep, $inner->dep);
        $outer = $injector->make('BrightNucleus\Injector\Tests\ClassOuter');
        $this->assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $obj      = $injector->make('BrightNucleus\Injector\Tests\ClassOuter');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\ClassOuter', $obj);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\ClassInnerA', $obj->dep);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\ClassInnerB', $obj->dep->dep);
    }

    public function provideCyclicDependencies()
    {
        return array(
            'BrightNucleus\Injector\Tests\RecursiveClassA' => array('BrightNucleus\Injector\Tests\RecursiveClassA'),
            'BrightNucleus\Injector\Tests\RecursiveClassB' => array('BrightNucleus\Injector\Tests\RecursiveClassB'),
            'BrightNucleus\Injector\Tests\RecursiveClassC' => array('BrightNucleus\Injector\Tests\RecursiveClassC'),
            'BrightNucleus\Injector\Tests\RecursiveClass1' => array('BrightNucleus\Injector\Tests\RecursiveClass1'),
            'BrightNucleus\Injector\Tests\RecursiveClass2' => array('BrightNucleus\Injector\Tests\RecursiveClass2'),
            'BrightNucleus\Injector\Tests\DependsOnCyclic' => array('BrightNucleus\Injector\Tests\DependsOnCyclic'),
        );
    }

    /**
     * @dataProvider provideCyclicDependencies
     */
    public function testCyclicDependencies($class)
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_CYCLIC_DEPENDENCY);
        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $class    = $injector->make('BrightNucleus\Injector\Tests\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertNull($class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias(
            'BrightNucleus\Injector\Tests\DelegatableInterface',
            'BrightNucleus\Injector\Tests\ImplementsInterface'
        );
        $class = $injector->make('BrightNucleus\Injector\Tests\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\ImplementsInterface', $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\DelegatableInterface',
            'BrightNucleus\Injector\Tests\ImplementsInterfaceFactory');
        $class = $injector->make('BrightNucleus\Injector\Tests\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\ImplementsInterface', $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare()
    {
        $injector = new Injector(ConfigFactory::create([]));
        //Instance is not shared, null default is used for dependency
        $instance = $injector->make('BrightNucleus\Injector\Tests\ConcreteDependencyWithDefaultValue');
        $this->assertNull($instance->dependency);

        //Instance is explicitly shared, $instance is used for dependency
        $instance = new stdClass();
        $injector->share($instance);
        $instance = $injector->make('BrightNucleus\Injector\Tests\ConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('stdClass', $instance->dependency);
    }

    public function testShareAfterAliasException()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $testClass = new stdClass();
        $injector->alias('stdClass', 'BrightNucleus\Injector\Tests\SomeOtherClass');
        $this->expectException('BrightNucleus\Injector\Exception\ConfigException');
        $this->expectExceptionCode(InjectorException::E_ALIASED_CANNOT_SHARE);
        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $testClass = new DepImplementation();
        $injector->alias('BrightNucleus\Injector\Tests\DepInterface', 'BrightNucleus\Injector\Tests\DepImplementation');
        $injector->share($testClass);
        $obj = $injector->make('BrightNucleus\Injector\Tests\DepInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\DepImplementation', $obj);
    }

    public function testAliasAfterShareByStringAllowed()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\DepInterface');
        $injector->alias('BrightNucleus\Injector\Tests\DepInterface', 'BrightNucleus\Injector\Tests\DepImplementation');
        $obj  = $injector->make('BrightNucleus\Injector\Tests\DepInterface');
        $obj2 = $injector->make('BrightNucleus\Injector\Tests\DepInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\DepImplementation');
        $injector->alias('BrightNucleus\Injector\Tests\DepInterface', 'BrightNucleus\Injector\Tests\DepImplementation');
        $obj  = $injector->make('BrightNucleus\Injector\Tests\DepInterface');
        $obj2 = $injector->make('BrightNucleus\Injector\Tests\DepInterface');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareException()
    {
        $injector  = new Injector(ConfigFactory::create([]));
        $testClass = new stdClass();
        $injector->share($testClass);
        $this->expectException('BrightNucleus\Injector\Exception\ConfigException');
        $this->expectExceptionCode(InjectorException::E_SHARED_CANNOT_ALIAS);
        $injector->alias('stdClass', 'BrightNucleus\Injector\Tests\SomeOtherClass');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_NON_PUBLIC_CONSTRUCTOR);
        $injector->make('BrightNucleus\Injector\Tests\HasNonPublicConstructor');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_NON_PUBLIC_CONSTRUCTOR);
        $injector->make('BrightNucleus\Injector\Tests\HasNonPublicConstructorWithArgs');
    }

    public function testMakeExecutableFailsOnNonExistentFunction()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionMessage('nonExistentFunction');
        $this->expectExceptionCode(InjectorException::E_INVOKABLE);
        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $object = new stdClass();
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionMessage("[object(stdClass), 'nonExistentMethod']");
        $this->expectExceptionCode(InjectorException::E_INVOKABLE);
        $injector->buildExecutable(array($object, 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionMessage("stdClass::nonExistentMethod");
        $this->expectExceptionCode(InjectorException::E_INVOKABLE);
        $injector->buildExecutable(array('stdClass', 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnClassWithoutInvoke()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $object = new stdClass();
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_INVOKABLE);
        $injector->buildExecutable($object);
    }

    public function testBadAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\DepInterface');
        $this->expectException('BrightNucleus\Injector\Exception\ConfigException');
        $this->expectExceptionCode(InjectorException::E_NON_EMPTY_STRING_ALIAS);
        $injector->alias('BrightNucleus\Injector\Tests\DepInterface', '');
    }

    public function testShareNewAlias()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\DepImplementation');
        $injector->alias('BrightNucleus\Injector\Tests\DepInterface', 'BrightNucleus\Injector\Tests\DepImplementation');
        $this->assertTrue(true);
    }

    public function testDefineWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Tests\SimpleNoTypehintClass', array(':arg' => 'tested'));
        $testClass = $injector->make('BrightNucleus\Injector\Tests\SimpleNoTypehintClass');
        $this->assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('stdClass');
        $classA         = $injector->make('stdClass');
        $classA->tested = false;
        $classB         = $injector->make('stdClass');
        $classB->tested = true;

        $this->assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->prepare('stdClass', function ($obj, $injector) {
            $obj->testval = 42;
        });
        $obj = $injector->make('stdClass');

        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->prepare('BrightNucleus\Injector\Tests\SomeInterface',
            function ($obj, $injector) {
                $obj->testProp = 42;
            });
        $obj = $injector->make('BrightNucleus\Injector\Tests\PreparesImplementationTest');

        $this->assertSame(42, $obj->testProp);
    }

    public function testCustomDefinitionNotPassedThrough()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\DependencyWithDefinedParam');
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_UNDEFINED_PARAM);
        $injector->make('BrightNucleus\Injector\Tests\RequiresDependencyWithDefinedParam', array(':foo' => 5));
    }

    public function testDelegationFunction()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\TestDelegationSimple',
            'BrightNucleus\Injector\Tests\createTestDelegationSimple');
        $obj = $injector->make('BrightNucleus\Injector\Tests\TestDelegationSimple');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\TestDelegationSimple', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate(
            'BrightNucleus\Injector\Tests\TestDelegationDependency',
            'BrightNucleus\Injector\Tests\createTestDelegationDependency'
        );
        $obj = $injector->make('BrightNucleus\Injector\Tests\TestDelegationDependency');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\TestDelegationDependency', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\BaseExecutableClass',
            'BrightNucleus\Injector\Tests\ExtendsExecutableClass');
        $result = $injector->execute(array(
            'BrightNucleus\Injector\Tests\BaseExecutableClass',
            'foo',
        ));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->alias('BrightNucleus\Injector\Tests\BaseExecutableClass',
            'BrightNucleus\Injector\Tests\ExtendsExecutableClass');
        $result = $injector->execute(array(
            'BrightNucleus\Injector\Tests\BaseExecutableClass',
            'bar',
        ));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside
     * of a class.ph
     *
     * @throws \BrightNucleus\Injector\Exception\ConfigException
     */
    public function testDelegateClosure()
    {
        $delegateClosure = \BrightNucleus\Injector\Tests\getDelegateClosureInGlobalScope();
        $injector        = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\DelegateClosureInGlobalScope', $delegateClosure);
        $obj = $injector->make('BrightNucleus\Injector\Tests\DelegateClosureInGlobalScope');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\DelegateClosureInGlobalScope', $obj);
    }

    public function testCloningWithServiceLocator()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share($injector);
        $instance    = $injector->make('BrightNucleus\Injector\Tests\CloneTest');
        $newInjector = $instance->injector;
        $newInstance = $newInjector->make('BrightNucleus\Injector\Tests\CloneTest');
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\CloneTest', $instance);
        $this->assertInstanceOf('BrightNucleus\Injector\Tests\CloneTest', $newInstance);
    }

    public function testAbstractExecute()
    {
        $injector = new Injector(ConfigFactory::create([]));

        $fn = function () {
            return new ConcreteExexcuteTest();
        };

        $injector->delegate('BrightNucleus\Injector\Tests\AbstractExecuteTest', $fn);
        $result = $injector->execute(array(
            'BrightNucleus\Injector\Tests\AbstractExecuteTest',
            'process',
        ));

        $this->assertEquals('Concrete', $result);
    }

    public function testDebugMake()
    {
        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->make('BrightNucleus\Injector\Tests\DependencyChainTest');
        } catch (InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            $this->assertCount(2, $chain);

            $this->assertEquals('BrightNucleus\Injector\Tests\DependencyChainTest', $chain[0]);
            $this->assertEquals('BrightNucleus\Injector\Tests\DepInterface', $chain[1]);
        }
    }

    public function testInspectShares()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->share('BrightNucleus\Injector\Tests\SomeClassName');

        $inspection = $injector->inspect('BrightNucleus\Injector\Tests\SomeClassName', Injector::I_SHARES);
        $this->assertArrayHasKey('BrightNucleus\Injector\Tests\SomeClassName', $inspection[Injector::I_SHARES]);
    }

    public function testDelegationDoesntMakeObject()
    {
        $delegate = function () {
            return null;
        };
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\SomeClassName', $delegate);
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_MAKING_FAILED);
        $injector->make('BrightNucleus\Injector\Tests\SomeClassName');
    }

    public function testDelegationDoesntMakeObjectMakesString()
    {
        $delegate = function () {
            return 'ThisIsNotAClass';
        };
        $injector = new Injector(ConfigFactory::create([]));
        $injector->delegate('BrightNucleus\Injector\Tests\SomeClassName', $delegate);
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_MAKING_FAILED);
        $injector->make('BrightNucleus\Injector\Tests\SomeClassName');
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare('BrightNucleus\Injector\Tests\SomeInterface',
            function ($impl) use ($expected) {
                return $expected;
            });
        $actual = $injector->make('BrightNucleus\Injector\Tests\SomeImplementation');
        $this->assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare('BrightNucleus\Injector\Tests\SomeImplementation',
            function ($impl) use ($expected) {
                return $expected;
            });
        $actual = $injector->make('BrightNucleus\Injector\Tests\SomeImplementation');
        $this->assertSame($expected, $actual);
    }

    public function testChildWithoutConstructorWorks()
    {

        $injector = new Injector(ConfigFactory::create([]));
        try {
            $injector->define('BrightNucleus\Injector\Tests\ParentWithConstructor', array(':foo' => 'parent'));
            $injector->define('BrightNucleus\Injector\Tests\ChildWithoutConstructor', array(':foo' => 'child'));

            $injector->share('BrightNucleus\Injector\Tests\ParentWithConstructor');
            $injector->share('BrightNucleus\Injector\Tests\ChildWithoutConstructor');

            $child = $injector->make('BrightNucleus\Injector\Tests\ChildWithoutConstructor');
            $this->assertEquals('child', $child->foo);

            $parent = $injector->make('BrightNucleus\Injector\Tests\ParentWithConstructor');
            $this->assertEquals('parent', $parent->foo);
        } catch (InjectionException $ie) {
            echo $ie->getMessage();
            $this->fail('Auryn failed to locate the ');
        }
    }

    public function testChildWithoutConstructorMissingParam()
    {
        $injector = new Injector(ConfigFactory::create([]));
        $injector->define('BrightNucleus\Injector\Tests\ParentWithConstructor', array(':foo' => 'parent'));
        $this->expectException('BrightNucleus\Injector\Exception\InjectionException');
        $this->expectExceptionCode(InjectorException::E_UNDEFINED_PARAM);
        $injector->make('BrightNucleus\Injector\Tests\ChildWithoutConstructor');
    }

    public function testInjectionChainValue()
    {

        $fn = function (InjectionChain $ic) {
            if ($ic->getByIndex(-2) ===
                'BrightNucleus\Injector\Tests\InjectionChainTestDependency'
            ) {
                return new InjectionChainValue('Value for dependency');
            } else if ($ic->getByIndex(-2) ===
                       'BrightNucleus\Injector\Tests\InjectionChainTest'
            ) {
                return new InjectionChainValue('Value for parent');
            }

            return new InjectionChainValue('unknown value');
        };

        $injector = new Injector(ConfigFactory::create([]));
        $injector->share($injector);
        $injector->delegate('BrightNucleus\Injector\Tests\InjectionChainValue', $fn);
        $injector->delegate('BrightNucleus\Injector\InjectionChain', [$injector, 'getInjectionChain']);

        $object = $injector->make('BrightNucleus\Injector\Tests\InjectionChainTest');
        $this->assertEquals($object->icv->value, 'Value for parent');
        $this->assertEquals($object->dependency->icv->value, 'Value for dependency');
    }
}
