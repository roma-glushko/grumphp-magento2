# grumphp-magento2

Magento 2 specific tasks for <a href="https://github.com/phpro/grumphp">GrumPHP</a> checker

## Installation

The easiest way to install this package is through composer:
	
	composer require --dev roma-glushko/grumphp-magento2

Add the extension loader to your `grumphp.yml`

```yaml
parameters:
    extensions:
        - Glushko\GrumphpMagento2\Extension\Loader
```
