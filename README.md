[![CI](https://github.com/optiosteam/teneo-mailer/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/optiosteam/teneo-mailer/actions/workflows/tests.yaml)
[![codecov](https://codecov.io/gh/optiosteam/teneo-mailer/branch/main/graph/badge.svg?token=S62YDUXV7A)](https://codecov.io/gh/optiosteam/teneo-mailer)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/optios/teneo-mailer)

# Teneo Bridge

Provides Teneo integration for Symfony Mailer.

## Documentation

[General documentation SymfonyMailer](https://symfony.com/doc/current/mailer.html#using-a-3rd-party-transport)

### Install 
```shell
composer require optios/teneo-mailer
```

### Configuration
```
# .env
MAILER_DSN=teneo://username:password@default
```
tip: username gets automatically suffixed with the host (@tlsrelay.teneo.be)