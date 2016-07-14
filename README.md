# Bright Nucleus Injector Component

> Config-driven extension of Auryn Dependency Injector.

[![Latest Stable Version](https://img.shields.io/packagist/v/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)
[![Total Downloads](https://img.shields.io/packagist/dt/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)
[![Latest Unstable Version](https://img.shields.io/packagist/vpre/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)
[![License](https://img.shields.io/packagist/l/brightnucleus/injector.svg)](https://packagist.org/packages/brightnucleus/injector)

This is an extension of the [Auryn](https://github.com/rdlowrey/auryn) dependency injector, to allow easy registration of alias mappings through the [`brightnucleus/config`](https://github.com/brightnucleus/config) component.

## Table Of Contents

* [Installation](#installation)
* [Basic Usage](#basic-usage)
    * [Standard Aliases](#standard-aliases)
    * [Shared Aliases](#shared-aliases)
    * [Argument Providers](#argument-providers)
* [Registering Additional Mappings](#registering-additional-mappings)
* [Contributing](#contributing)
* [License](#license)

## Installation

The best way to use this component is through Composer:

```BASH
composer require brightnucleus/injector
```

## Basic Usage

> This documentation only deals with passing in mappings through a `Config` file. For documentation of the actual dependency injection functionality, refer to the [Auryn README](https://github.com/rdlowrey/auryn/blob/master/README.md).

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

Standard aliases are defined through the `standardAliases` configuration key:

```PHP
// Format:
//    '<class/interface>' => '<concrete class to instantiate>',
'standardAliases' => [
    'BrightNucleus\Config\ConfigInterface' => 'BrightNucleus\Config\Config',
]
```

### Shared Aliases

A shared alias is an alias that behaves similarly to a static variable, in that they get reused across all instantiations. So, for each new instantiation (using `Injector::make()`), you'll get exactly teh same instance each time. The object is only truly instantiated the first time it is needed, and this instance is then shared.

Shared aliases are defined through the `sharedAliases` configuration key:

```PHP
// Format:
//    '<class/interface>' => '<concrete class to instantiate>',
'sharedAliases' => [
    'ShortcodeManager' => 'BrightNucleus\Shortcode\ShortcodeManager',
]
```

### Argument Providers

The argument providers allow you to let the `Injector` know what to pass in to arguments, like `$config` or `$logger`. As these are probably different for each object, we need a way to map them to specific aliases (instead of having one global value to pass in). This is done by mapping each alias to a callable that returns an object of the correct type.

As an example, pretty much all of the Bright Nucleus components use Config files to do project-specific work.

If you want to map aliases to specific subtrees of Config files, you can do this by providing a callable for each alias. When the `Injector` tries to instantiate that specific alias, it will invoke the corresponding callable and hopefully get a matching Config back.

```PHP
// Format:
// 'argument' => [
//    'interface' => '<interface/class that the argument accepts>',
//    'mappings'  => [
//        '<alias to provide argument for>' => <callable that returns a matching object>,
//    ],
// ],
'argumentProviders' => [
    'config' => [
        'interface' => ConfigInterface::class,
        'mappings'  => [
            'BrightNucleus\Shortcode\ShortcodeManager' => function ($alias, $interface) {
                return ConfigFactory::createSubConfig(
                    __DIR__ . '/config/defaults.php',
                    $alias
                );
            },
        ],
    ],
]
```

## Registering Additional Mappings

You can register additional mappings at any time by simply passing additional Configs to the `Injector::registerMappings()` method. It takes the exact same format as the constructor.

```PHP
$config = ConfigFactory::create([
    'standardAliases => [
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
