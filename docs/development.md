# Development

This guide covers local development setup, testing, and code quality tools for the Microsub plugin.

## Local Development with wp-env

This plugin includes a [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) configuration for local development.

### Prerequisites

- [Node.js](https://nodejs.org/) (LTS version recommended)
- [Docker](https://www.docker.com/) (required by wp-env)

### Getting Started

```bash
npm install
npm start
```

The local environment will be available at http://localhost:8686.

### wp-env Commands

```bash
# Start the environment
npm start

# Stop the environment
npm stop

# Destroy the environment (removes all data)
npm run destroy
```

## Running Tests

The plugin uses PHPUnit for testing.

### Prerequisites

```bash
composer install
```

### Running All Tests

```bash
composer test
```

### Running Specific Tests

```bash
# Run a specific test file
composer exec phpunit -- --filter TestClassName

# Run tests with coverage
composer exec phpunit -- --coverage-html coverage/
```

## Code Quality

### PHP CodeSniffer

The plugin follows WordPress Coding Standards.

```bash
# Check for coding standard violations
composer lint

# Automatically fix violations where possible
composer lint:fix
```

### Configuration

PHPCS configuration is defined in `phpcs.xml`. The plugin uses:

- WordPress coding standards
- PHPCompatibilityWP for PHP version compatibility
- VariableAnalysis for unused variable detection

## Debugging

### Enable Debug Mode

Add to your `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Viewing Logs

Debug output is written to `wp-content/debug.log`.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and linting
5. Submit a pull request
