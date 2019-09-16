# grumphp-magento2

Magento 2 specific tasks for <a href="https://github.com/phpro/grumphp">GrumPHP</a> checker

## Installation

The easiest way to install this package is through composer:
```bash
composer config repositories.grumphp-magento2 vcs https://github.com/roma-glushko/grumphp-magento2
composer require --dev roma-glushko/grumphp-magento2
```

Add the extension loader to your `grumphp.yml`

```yaml
parameters:
    extensions:
        - Glushko\GrumphpMagento2\Extension\Loader
```
