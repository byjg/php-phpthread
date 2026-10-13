# PHPThread 6.0 - Changelog

## Overview

PHPThread 6.0 is a major version update that brings PHP 8.3+ compatibility, improved stability, enhanced error handling, and comprehensive documentation. This release includes breaking changes and requires migration steps from version 5.x.

---

## New Features

### PHP 8.3+ Support
- Added support for PHP 8.3 and PHP 8.4
- Fixed PHP 8.4 deprecation warnings with proper nullable type declarations
- Added `#[\Override]` attributes to all methods implementing interfaces for better type safety

### CLI Mode Enforcement
- Added runtime validation to prevent execution in PHP-FPM and Apache mod_php environments
- Both `ForkHandler` and `ParallelHandler` now throw `RuntimeException` when not running in CLI mode
- Enhanced safety to prevent misuse in web server contexts

### Enhanced Promise Implementation
- Improved error handling with null-safety checks
- Better error messages when Promise results are null
- Changed from debug `echo` statements to proper `RuntimeException` throws
- Explicit nullable type declarations for the `$onRejected` parameter in `then()` method

### Documentation Improvements
- Added comprehensive CLI-only limitation documentation
- Created example files for Thread, ThreadPool, and Promise usage
- Added Docusaurus-compatible frontmatter to all documentation files
- Removed "Experimental" label from Promises feature (now production-ready)
- Added limitations.md documentation file
- Enhanced README with better structure and dependency diagrams

### Development Tools
- Updated PHPUnit workflow configuration with parameterized PHP versions
- Added composer scripts for `test` and `psalm` commands
- Updated .gitignore with additional coverage and report files
- Added Docker Compose configuration for development environment

---

## Bug Fixes

- Fixed implicitly nullable parameters in Promise interface and class
- Fixed formatting inconsistencies in composer.json
- Improved Promise error handling to prevent null pointer issues
- Enhanced ForkHandler with null checks for thread key initialization
- Better error messages throughout the codebase

---

## Breaking Changes

| Component | Before (5.x) | After (6.0) | Description |
|-----------|--------------|-------------|-------------|
| **PHP Version** | `>=8.1 <8.4` | `>=8.3 <8.6` | Minimum PHP version increased from 8.1 to 8.3. PHP 8.1 and 8.2 are no longer supported. |
| **Dependencies** | `byjg/cache-engine: ^5.0` | `byjg/cache-engine: ^6.0` | Updated to version 6.0 of cache-engine dependency. |
| **PHPUnit** | `^9.6` | `^10.5\|^11.5` | Major PHPUnit version upgrade. Test configuration schema updated. |
| **Psalm** | `^5.9` | `^5.9\|^6.13` | Added support for Psalm 6.x. |
| **Runtime Environment** | No validation | CLI mode required | Now throws `RuntimeException` if executed in non-CLI SAPI (PHP-FPM, Apache mod_php). This was always a limitation but is now enforced. |
| **Promise Interface** | `then(Closure $onFulfilled, Closure $onRejected = null)` | `then(Closure $onFulfilled, ?Closure $onRejected = null)` | Explicit nullable type declaration. No behavioral change, but signature is stricter. |
| **Error Handling** | Silent failures with echo | Throws `RuntimeException` | Promise operations now throw proper exceptions instead of echoing debug information. |
| **Composer Stability** | No minimum-stability | `minimum-stability: dev`<br>`prefer-stable: true` | Added stability preferences to composer.json. |

---

## Path to Upgrade from 5.x to 6.x

### Step 1: Check PHP Version
Ensure you're running PHP 8.3 or later:
```bash
php -v
```

If you're on PHP 8.1 or 8.2, you must upgrade to PHP 8.3+ before migrating to PHPThread 6.0.

### Step 2: Update Composer Dependencies
Update your `composer.json`:

```json
{
  "require": {
    "byjg/phpthread": "^6.0"
  }
}
```

Run composer update:
```bash
composer update byjg/phpthread
```

This will automatically update the `byjg/cache-engine` dependency to version 6.0.

### Step 3: Update Development Dependencies (if applicable)
If you have PHPUnit or Psalm in your project:

```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.5|^11.5",
    "vimeo/psalm": "^5.9|^6.13"
  }
}
```

### Step 4: Update PHPUnit Configuration
If you're using PHPUnit, update your `phpunit.xml.dist` to the PHPUnit 10.5+ schema:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="YourTestSuite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
</phpunit>
```

### Step 5: Verify CLI Mode Usage
Ensure all PHPThread usage is in CLI mode only. If you were attempting to use threads in a web context (which never worked properly), you must refactor to use:
- Message queues (RabbitMQ, Redis, etc.)
- Background job processors running in CLI mode
- Dedicated CLI workers

### Step 6: Update Error Handling
If you were catching or handling any echo output from Promise operations, update to catch `RuntimeException` instead:

**Before (5.x):**
```php
// Promise might echo debug info
$promise->await();
```

**After (6.0):**
```php
try {
    $result = $promise->await();
} catch (\RuntimeException $e) {
    // Handle promise errors properly
    error_log($e->getMessage());
}
```

### Step 7: Run Tests
After migration, run your test suite:
```bash
vendor/bin/phpunit
```

And static analysis:
```bash
vendor/bin/psalm
```

### Step 8: Review Code for PHP 8.3+ Compatibility
Check your codebase for:
- PHP 8.4 deprecation warnings
- Implicit nullable parameters (should be explicit: `?Type`)
- Missing `#[\Override]` attributes (if using Psalm with strict checks)

---

## Additional Notes

### New Composer Scripts
You can now use convenient composer scripts:
```bash
composer test    # Runs PHPUnit
composer psalm   # Runs Psalm with single thread
```

### Documentation
Visit the updated documentation for:
- [Thread Usage Examples](examples/thread.php)
- [ThreadPool Examples](examples/threadpool.php)
- [Promise Examples](examples/promisse.php)
- [Promises Benchmark](docs/promises-benchmark.md)

### Support
For issues or questions:
- Report bugs at: https://github.com/byjg/php-phpthread/issues
- Read the docs at: [Project Documentation](docs/)

---

## Contributors
Special thanks to all contributors who helped make this release possible!
