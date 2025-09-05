# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-01

### Added
- Initial release of Simple Exception package
- Enum-based error code system with `ThrowableEnum` interface
- Custom `ErrorResponse` exception class
- Laravel exception handler integration
- Multi-language support (English, Russian, Uzbek)
- Facade for easy package usage
- Comprehensive test suite
- Configuration file with customizable response keys
- Helper functions for error handling
- Service provider for Laravel integration
- Documentation and examples

### Features
- `MainRespCode` enum with predefined error codes
- Conditional error handling with `errorIf` and `errorUnless`
- Environment detection (`isDev`, `isProd`)
- Custom response building
- Validation error handling
- Maintenance mode response
- Trace debugging support
- Configurable response structure

### Technical Details
- PHP 8.2+ requirement
- Laravel 10+ support
- PSR-4 autoloading
- Composer package structure
- Orchestra Testbench for testing
- MIT License
