# Package Skeleton

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andreagroferreira/laravel-ai-sdk-toolbox.svg)](https://packagist.org/packages/andreagroferreira/laravel-ai-sdk-toolbox)
[![Total Downloads](https://img.shields.io/packagist/dt/andreagroferreira/laravel-ai-sdk-toolbox.svg)](https://packagist.org/packages/andreagroferreira/laravel-ai-sdk-toolbox)
[![Tests](https://github.com/andreagroferreira/laravel-ai-sdk-toolbox/actions/workflows/tests.yml/badge.svg)](https://github.com/andreagroferreira/laravel-ai-sdk-toolbox/actions/workflows/tests.yml)

Short description of the package.

## Installation

```bash
composer require andreagroferreira/laravel-ai-sdk-toolbox
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=laravel-ai-sdk-toolbox-config
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=laravel-ai-sdk-toolbox-migrations
php artisan migrate
```

## Usage

```php
use AndreAgroFerreira\LaravelAiSdkToolbox\Facades\LaravelAiSdkToolbox;

LaravelAiSdkToolbox::version();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [André Agro Ferreira](https://github.com/andreagroferreira)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
