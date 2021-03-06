# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [0.4.3] - 2018-01-17
### Changed
- Opened up the requirements to be more forgiving.

## [0.4.2] - 2017-03-27
### Changed
- Use key constants of raw config keys.
- Remove `composer.lock` file.
- Improve error message on undefined parameters.

## [0.4.1] - 2017-02-11
### Added
- Added `BrightNucleus\Injector\Injection` class as a mechanism to pass instantiatable aliases to the argument definitions key.

## [0.4.0] - 2017-02-10
### Changed
- Remove `K_` prefix from key constants.

## [0.3.1] - 2017-01-07
### Changed
- Limit ProxyManager version to 2.0 branch, 2.1 breaks BC.

## [0.3.0] - 2016-07-25
### Added
- Added support for `'delegations'` key.
- The instantiation chain can be queried from within delegate closures.

### Changed
- Completely integrated former Auryn code dependency, to be able to take the code in a different direction where needed.
- Aliases are case-sensitive now.

## [0.2.4] - 2016-07-22
### Added
- Added support for `'preparations'` key.

## [0.2.3] - 2016-07-21
### Added
- Added support for `'argumentDefinitions'` key.

## [0.2.2] - 2016-07-14
### Changed
- Changed arguments for `Injector::getArgumentProxy($alias, $interface, $callable)`.

## [0.2.1] - 2016-07-07
### Fixed
- Fixed bug in argument definitions parsing.

## [0.2.0] - 2016-07-07
### Added
- Added functionality to parse 'argumentProviders' keys, letting you map arbitrary arguments to arbitrary aliases.

### Removed
- Removed 'configFiles' key parsing. Replaced by the more general-purpose 'argumentProviders' key.

### Changed
- Updated composer dependencies.

## [0.1.2] - 2016-04-28
### Fixed
- Updated table of contents in `README.md`.

## [0.1.1] - 2016-04-28
### Added
- Added documentation about `Injector::registerMappings()`.

## [0.1.0] - 2016-04-28
### Added
- Initial release to GitHub.

[0.4.2]: https://github.com/brightnucleus/injector/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/brightnucleus/injector/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/brightnucleus/injector/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/brightnucleus/injector/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/brightnucleus/injector/compare/v0.2.4...v0.3.0
[0.2.4]: https://github.com/brightnucleus/injector/compare/v0.2.3...v0.2.4
[0.2.3]: https://github.com/brightnucleus/injector/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/brightnucleus/injector/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/brightnucleus/injector/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/brightnucleus/injector/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/brightnucleus/injector/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/brightnucleus/injector/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/brightnucleus/injector/compare/v0.0.0...v0.1.0
