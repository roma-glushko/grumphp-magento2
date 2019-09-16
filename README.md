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

## Usage

### MagentoModuleRegistration

It's a common practice to commit config.php file in Magento 2. Especially, the file is useful for managing modules. The common issue is when during development people forget to register newly added modules to the config.php which can lead to outcomes that hard to troubleshoot. This task helps to watch for such cases and let to know when registration is missing.

To use this task, just specify if inside `grumphp.yml` in the `tasks:` section.

```yaml
parameters:
    tasks:
        magento2-module-registration:
            composer_json_path: ~
            composer_home_path: ~
            configphp_path: ~
            allowed_package_types: ~
            custom_module_pattern: ~
            allowed_packages:
                magento/data-migration-tool: ["Magento_DataMigrationTool"]
                another-vendor/cool-module: ["AnotherVendor_CoolModule"]
            
```
