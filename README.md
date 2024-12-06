# MakegentoCli module

[![Latest Stable Version](https://img.shields.io/packagist/v/opengento/module-makegento-cli.svg?style=flat-square)](https://packagist.org/packages/opengento/module-makegento-cli)
[![License: MIT](https://img.shields.io/github/license/opengento/magento2-makegento-cli.svg?style=flat-square)](./LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/opengento/module-makegento-cli.svg?style=flat-square)](https://packagist.org/packages/opengento/module-makegento-cli/stats)
[![Packagist](https://img.shields.io/packagist/dm/opengento/module-makegento-cli.svg?style=flat-square)](https://packagist.org/packages/opengento/module-makegento-cli/stats)

This extension allows to automatically generate boilerplate code through the command line interface.

- [Setup](#setup)
    - [Composer installation](#composer-installation)
    - [Setup the module](#setup-the-module)
- [Features](#features)
- [Support](#support)
- [Authors](#authors)
- [License](#license)

## Setup

Magento 2 Open Source or Commerce edition is required.

### Composer installation

Run the following composer command:

```
composer require opengento/magento2-makegento-cli
```

### Setup the module

Run the following magento command:

```
bin/magento setup:upgrade
```

**If you are in production mode, do not forget to recompile and redeploy the static resources.**

## Features

- Generate CRUD entity with all required files ( routes, controllers, models,acl,grids,forms etc. )

You can find all the related commands by running the following command:

```
bin/magento list makegento
```

### Commands documentation
[Make entity docs](docs/make-entity.md)

## Support

Raise a new [request](https://github.com/opengento/magento2-makegento-cli/issues) to the issue tracker.

## Authors

- **Opengento Community** - *Lead* - [![Twitter Follow](https://img.shields.io/twitter/follow/opengento.svg?style=social)](https://twitter.com/opengento)
- **Contributors** - *Contributor* - [![GitHub contributors](https://img.shields.io/github/contributors/opengento/magento2-makegento-cli.svg?style=flat-square)](https://github.com/opengento/magento2-makegento-cli/graphs/contributors)




