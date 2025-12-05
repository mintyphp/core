# MintyPHP Core

A lightweight, minimalist PHP framework providing essential components for modern web applications.

## Requirements

- **PHP**: >= 8.0
- **Extensions**:
  - `ext-memcache`: Required for caching functionality
  - `ext-mysqli`: Required for database operations

## Installation

Install MintyPHP Core via Composer:

```bash
composer require mintyphp/core
```

## Development

### Project Structure

The project follows a dual-layer architecture:

- **`src/Core/`**: Contains the core implementation classes (e.g., `Core/Template.php`, `Core/Router.php`)
- **`src/`**: Contains auto-generated static wrapper classes (e.g., `Template.php`, `Router.php`)

### Important: Do NOT Edit Generated Classes

**The wrapper classes in `src/` are auto-generated.** Do not manually edit these files as your changes will be overwritten.

### Making Changes

1. **Edit Core Classes**: Make all changes to files in `src/Core/` directory
2. **Regenerate Wrappers**: After modifying core classes, regenerate the wrapper classes:

```bash
php generate_wrappers.php
```

This script automatically generates static wrapper classes that provide a convenient facade pattern for the instance-based Core classes.

### Development Workflow

```bash
# 1. Make changes to core classes
nano src/Core/Template.php

# 2. Regenerate wrapper classes
php generate_wrappers.php

# 3. Run tests to verify changes
./test.sh

# 4. Run static analysis (optional)
vendor/bin/phpstan analyse
```

## Running Tests

### Run All Tests

```bash
./test.sh
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit tests
```

### Run Specific Test Class

```bash
vendor/bin/phpunit tests/Core/RouterTest.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter testAdmin tests/Core/RouterTest.php
```

### Run with Coverage (if Xdebug is installed)

```bash
vendor/bin/phpunit --coverage-html coverage
```

## Static Analysis

Run PHPStan for static code analysis:

```bash
vendor/bin/phpstan analyse
```

## Components

MintyPHP Core provides the following components:

- **Router**: URL routing and request handling
- **Template**: Template rendering engine
- **DB**: Database abstraction layer
- **Auth**: Authentication management
- **Session**: Session handling
- **Cache**: Caching mechanisms
- **Firewall**: Security and access control
- **Orm**: Object-relational mapping
- **Buffer**: Output buffering
- **Curl**: HTTP client wrapper
- **Token**: Token generation and validation
- **Totp**: Time-based one-time password support
- **I18n**: Internationalization support
- **NoPassAuth**: Passwordless authentication
- **Analyzer**: Code analysis utilities
- **Debugger**: Debugging and profiling tools

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.

## Author

**Maurits van der Schee**
- Email: maurits@vdschee.nl
- Homepage: https://www.tqdev.com
