<p align="center">
  <a href="https://croct.com" target="_blank">
    <picture>
        <source media="(min-width: 769px) and (prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/croct-tech/plug-php/master/.github/assets/header-light.svg">
        <source media="(min-width: 769px) and (prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/croct-tech/plug-php/master/.github/assets/header-dark.svg">
        <source media="(max-width: 768px) and (prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/croct-tech/plug-php/master/.github/assets/header-dark-mobile.svg">
        <source media="(max-width: 768px) and (prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/croct-tech/plug-php/master/.github/assets/header-light-mobile.svg">
        <img src="https://raw.githubusercontent.com/croct-tech/plug-php/master/.github/assets/header-light-mobile.svg" alt="Croct Laravel Package" title="Croct Laravel Package" width="100%">
    </picture>
  </a>
  <br/>
  <strong>Croct Laravel Package</strong><br/>
  Bring dynamic, personalized content natively into your Laravel applications.
</p>
<div align="center">
    <strong>📘 <a href="https://docs.croct.com/reference/sdk/laravel/installation">Quick start &rarr;</a></strong>
</div>
<br/>
<p align="center">
    <a href="https://packagist.org/packages/croct/plug-laravel"><img alt="Version" src="https://img.shields.io/packagist/v/croct/plug-laravel"/></a>
    <a href="https://github.com/croct-tech/plug-laravel/actions/workflows/validate-branch.yaml"><img alt="Build" src="https://github.com/croct-tech/plug-laravel/actions/workflows/validate-branch.yaml/badge.svg"/></a>
</p>

## Introduction

Croct is a headless CMS that helps you manage content, run AB tests, and personalize experiences without the hassle of complex integrations.

This package provides seamless integration between Croct and Laravel, automatically bootstrapping the client-side SDK, syncing the identity from the authenticated user, and resolving the locale from the application locale so you can deliver personalized content with no glue code.

## Installation

Run this command to install the package:

```sh
composer require croct/plug-laravel
```

Then set your credentials in the `.env` file:

```dotenv
CROCT_APP_ID=<APP_ID>
CROCT_API_KEY=<API_KEY>
```

The package auto-registers through Laravel package discovery, so there is no need to wire up the service provider or middleware manually.

Optionally, publish the configuration file to customize the package:

```sh
php artisan vendor:publish --tag=croct-config
```

See our [quick start guide](https://docs.croct.com/reference/sdk/laravel/installation) for more details.

## Documentation

Visit our [official documentation](https://docs.croct.com/reference/sdk/laravel/installation).

## Support

Join our official [Slack channel](https://croct.link/community) to get help from the Croct team and other developers.

## Contribution

Contributions are always welcome!

- Report any bugs or issues on the [issue tracker](https://github.com/croct-tech/plug-laravel/issues).
- For major changes, please [open an issue](https://github.com/croct-tech/plug-laravel/issues) first to discuss what you would like to change.
- Please make sure to update tests as appropriate. Run tests with `composer test`.

## License

This library is licensed under the [MIT license](LICENSE).
