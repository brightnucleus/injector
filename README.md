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

### Config Files

Pretty much all of the Bright Nucleus components use Config files to do project-specific work. That's why the Bright Nucleus Injector comes with support for Config files directly built in (using `brightnucleus/config`).

You can map aliases to specific subtrees of Config files. This allows you to either have each object get its own Config file, or have all the objects share the same Config file, or anything in-between. If you specify the same file multiple times, `Injector` will cache that file and only ever load it once into memory.

__Note__: For this to work, the `Injector` assumes that you pass your Config files through a constructor argument named `$config`, and typehinted to `BrightNucleus\Config\ConfigInterface`.

Config files are defined through the `configFiles` configuration key:

```PHP
// Format:
//    '<class/interface>' => [
//        'path'   => '<path & name of config file>',
//        'subKey' => '<key to fetch for the sub-config>',
//    ],
'configFiles' => [
    'ShortcodeManager' => [
        'path'   => __DIR__ . '/config/defaults.php',
        'subKey' => 'BrightNucleus\Example\ShortcodeManager',
    ]
]
```

## Contributing

All feedback / bug reports / pull requests are welcome.

This package uses the [PHP Composter PHPCS PSR-2](https://github.com/php-composter/php-composter-phpcs-psr2) package to check committed files for compliance with the [PSR-2 Coding Style Guide](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md). If you have valid reasons to skip this check, add the `--no-verify` option to your commit command:
```BASH
git commit --no-verify
```

## License

This code is released under the MIT license.

For the full copyright and license information, please view the [`LICENSE`](LICENSE) file distributed with this source code.
