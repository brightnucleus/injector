# Bright Nucleus Injector Component

> Config-driven Dependency Injector, based in large parts on Auryn.

[![Latest Stable Version](https://img.shields.io/packagist/v/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)
[![Total Downloads](https://img.shields.io/packagist/dt/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)
[![Latest Unstable Version](https://img.shields.io/packagist/vpre/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)
[![License](https://img.shields.io/packagist/l/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)

This is a config-driven dependency injector, to allow easy registration of alias mappings through the [`brightnucleus/config`](https://github.com/brightnucleus/config) component.

It includes large parts of code from the [`rdlowrey/auryn`](https://github.com/rdlowrey/auryn) package.

Notable changes compared to Auryn:

* Injector configuration can be done through a Config file.
* Aliases are case-sensitive.
* Closures can receive an `InjectionChain` object that let you iterate over the instantiation hierarchy.

## Table Of Contents

* [Requirements](#requirements)
* [Installation](#installation)
* [Basic Usage](#basic-usage)
    * [Standard Aliases](#standard-aliases)
    * [Shared Aliases](#shared-aliases)
    * [Argument Definitions](#argument-definitions)
    * [Argument Providers](#argument-providers)
    * [Delegations](#delegations)
    * [Preparations](#preparations)
* [Registering Additional Mappings](#registering-additional-mappings)
* [Contributing](#contributing)
* [License](#license)

## Requirements

BrightNucleus Injector requires PHP 7.0+.

## Installation

The best way to use this component is through Composer:

```BASH
composer require brightnucleus/injector
```

## Basic Usage

> This documentation only deals with passing in mappings through a `Config` file. Documentation for the basic methods still needs to be synced. For now, just refer to the [Auryn README](https://github.com/rdlowrey/auryn/blob/master/README.md) for these..

The Bright Nucleus Injector expects to get an object through its constructor that implements the `BrightNucleus\Config\ConfigInterface`. You need to pass in the correct "sub-configuration", so that the keys that the `Injector` is looking for are found at the root level.

The `Injector` looks for three configuration keys: `standardAliases`, `sharedAliases` and `configFiles`.

The injector works by letting you map aliases to implementations. An alias is a specific name that you want to be able to instantiate. Aliases can be classes, abstract classes, interfaces or arbitrary strings. You can map each alias to a concrete implementation that the injector should instantiate.

This allows you to have your classes only depend on interfaces, through constructor injection, and choose the specific implementation to use through the injector config file.

As an example, imagine the following class:

```PHP
class BookReader
{
    /** @var BookInterface */
    protected $book;

    public function __construct(BookInterface $book)
    {
        $this->book = $book;
    }

    public function read()
    {
        echo $this->book->getContents();
    }
}
```

If we now define an alias `'BookInterface' => 'LatestBestseller'`, we can have code like the following:

```PHP
<?php
$bookReader = $injector->make('BookReader');
// This will now echo the result of LatestBestseller::getContents().
$bookReader->read();
```

### Standard Aliases

A standard alias is an alias that behaves like a normal class. So, for each new instantiation (using `Injector::make()`), you'll get a fresh new instance.

Standard aliases are defined through the `Injector::STANDARD_ALIASES` configuration key:

```PHP
// Format:
//    '<class/interface>' => '<concrete class to instantiate>',
Injector::STANDARD_ALIASES => [
    'BrightNucleus\Config\ConfigInterface' => 'BrightNucleus\Config\Config',
]
```

### Shared Aliases

A shared alias is an alias that behaves similarly to a static variable, in that they get reused across all instantiations. So, for each new instantiation (using `Injector::make()`), you'll get exactly the same instance each time. The object is only truly instantiated the first time it is needed, and this instance is then shared.

Shared aliases are defined through the `Injector::SHARED_ALIASES` configuration key:

```PHP
// Format:
//    '<class/interface>' => '<concrete class to instantiate>',
Injector::SHARED_ALIASES => [
    'ShortcodeManager' => 'BrightNucleus\Shortcode\ShortcodeManager',
]
```

### Argument Definitions

The argument definitions allow you to let the `Injector` know what to pass in to arguments when you need to inject scalar values.

```PHP
// Format:
//
// '<alias to provide argument for>' => [
//    '<argument>' => '<callable or scalar that returns the value>',
// ],
Injector::ARGUMENT_DEFINITIONS => [
	'PDO' => [
		'dsn'      => $dsn,
		'username' => $username,
		'passwd'   => $password,
	]
]
```

By default, the values you pass in as definitions are assumed to be raw values to be used as they are. If you want to pass in an alias through the `Injector::ARGUMENT_DEFINITIONS` key, wrap it in a `BrightNucleus\Injector\Injcetion` class, like so:

```PHP
Injector::ARGUMENT_DEFINITIONS => [
	'config' => [
		'config' => new Injection( 'My\Custom\ConfigClass' ),
	]
]
```


### Argument Providers

The argument providers allow you to let the `Injector` know what to pass in to arguments when you need to instantiate objects, like `$config` or `$logger`. As these are probably different for each object, we need a way to map them to specific aliases (instead of having one global value to pass in). This is done by mapping each alias to a callable that returns an object of the correct type.

The Injector will create a light-weight proxy object for each of these. These proxies are instantiated and replaced by the real objects when they are first referenced.

As an example, pretty much all of the Bright Nucleus components use Config files to do project-specific work.

If you want to map aliases to specific subtrees of Config files, you can do this by providing a callable for each alias. When the `Injector` tries to instantiate that specific alias, it will invoke the corresponding callable and hopefully get a matching Config back.

```PHP
// Format:
// '<argument>' => [
//    'interface' => '<interface/class that the argument accepts>',
//    'mappings'  => [
//        '<alias to provide argument for>' => <callable that returns a matching object>,
//    ],
// ],
Injector::ARGUMENT_PROVIDERS => [
    'config' => [
        'interface' => ConfigInterface::class,
        'mappings'  => [
            ShortcodeManager::class => function ($alias, $interface) {
                return ConfigFactory::createSubConfig(
                    __DIR__ . '/config/defaults.php',
                    $alias
                );
            },
        ],
    ],
]
```

### Delegations

The delegations allow you to let the `Injector` delegate the instantiation for a given alias to a provided factory. The factory can be any callable that will return an object that is of a matching type to satisfy the alias.

If you need to act on the injection chain, like finding out what the object is for which you currently need to instantiate a dependency, add a `BrightNucleus\Injector\InjectionChain $injectionChain` argument to your factory callable. You will then be able to query the passed-in injection chain. To query the injection chain, pass the index you want to fetch into `InjectionChain::getByIndex($index)`. If you provide a negative index, you will get the nth element starting from the end of the queue counting backwards.

As an example, consider an `ExampleClass` with a constructor `__construct( ExampleDependency $dependency )`. The injection chain would be the following (in `namespace Example\Namespace`) :

```
[0] => 'Example\Namespace\ExampleClass'
[1] => 'Example\Namespace\ExampleDependency'
```

So, in the example below, we use `getByIndex(-2)` to fetch the second-to-last element from the list of injections.

```PHP
// Format:
//    '<alias>' => <callable to use as factory>
Injector::DELEGATIONS => [
	'Example\Namespace\ExampleDependency' => function ( InjectionChain $injectionChain ) {
		$parent = $injectionChain->getByIndex(-2);
		$factory = new \Example\Namespace\ExampleFactory();
		return $factory->createFor( $parent );
	},
]
```

### Preparations

The preparations allow you to let the `Injector` define additional preparation steps that need to be done after instantiation, but before the object if actually used.

The callable will receive two arguments, the object to prepare, as well as a reference to the injector.

```PHP
// Format:
//    '<alias>' => <callable to execute after instantiation>
Injector::PREPARATIONS => [
	'PDO' => function ( $instance, $injector ) {
		/** @var $instance PDO */
		$instance->setAttribute(
			PDO::ATTR_DEFAULT_FETCH_MODE,
			PDO::FETCH_OBJ
		);
	},
]
```

## Registering Additional Mappings

You can register additional mappings at any time by simply passing additional Configs to the `Injector::registerMappings()` method. It takes the exact same format as the constructor.

```PHP
$config = ConfigFactory::create([
    Injector::STANDARD_ALIASES => [
        'ExampleInterface' => 'ConcreteExample'
    ]
]);
$injector->registerMappings($config);
// Here, `$object` will be an instance of `ConcreteExample`.
$object = $injector->make('ExampleInterface');
```

__Note__: For such a simple example, creating a configuration file is of course overkill. You can just as well use the basic `Auryn` [alias functionality](https://github.com/rdlowrey/auryn/blob/master/README.md#type-hint-aliasing) and just `Injector::alias()` an additional alias. Refer to the [Auryn Documentation](https://github.com/rdlowrey/auryn/blob/master/README.md#type-hint-aliasing) to read more about the different ways of configuring the injector manually.

## Contributing

All feedback / bug reports / pull requests are welcome.

This package uses the [PHP Composter PHPCS PSR-2](https://github.com/php-composter/php-composter-phpcs-psr2) package to check committed files for compliance with the [PSR-2 Coding Style Guide](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md). If you have valid reasons to skip this check, add the `--no-verify` option to your commit command:
```BASH
git commit --no-verify
```

## License

This code is released under the MIT license.

For the full copyright and license information, please view the [`LICENSE`](LICENSE) file distributed with this source code.

Large parts of this code were initially taken from the [`rdlowrey/auryn`](https://github.com/rdlowrey/auryn) project.

Copyright for the original Auryn code is (c) 2013-2014 Daniel Lowrey, Levi Morrison, Dan Ackroyd.

[Auryn contributor list](https://github.com/rdlowrey/auryn/graphs/contributors)
