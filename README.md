# grumphp-magento2

Magento 2 specific tasks for <a href="https://github.com/phpro/grumphp">GrumPHP</a> checker

## Installation

The easiest way to install this package is through composer:
```bash
composer require --dev roma-glushko/grumphp-magento2
```

Add the extension loader to your `grumphp.yml`

```yaml
parameters:
    extensions:
        - Glushko\GrumphpMagento2\Extension\Loader
```

## Usage

### 🛠 MagentoModuleRegistration

It's a common practice to commit config.php file in Magento 2. Especially, the file is useful for managing modules. The common issue is when during development people forget to register newly added modules to the config.php which can lead to outcomes that hard to troubleshoot. This task helps to watch for such cases and let to know when registration is missing.

To use this task, just specify this inside `grumphp.yml` in the `tasks:` section.

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

**composer_json_path**

*Default: `./composer.json`*

Path to composer.json file of the project. 
This file will be used to find Magento modules installed via Composer.

**composer_home_path**

*Default: `./var/composer_home`*

Path to Composer Home directory.

**configphp_path**

*Default: `./app/etc/config.php`*

Path to config.php file of the project.

**allowed_package_types**

*Default: `['magento2-module', 'magento2-component']`*

Magento package types that we need to track during checking of packages. 
Sometimes vendors don't specify package type, but normally they should.

**allowed_packages**

*Default: `["magento/data-migration-tool": ["Magento_DataMigrationTool"]]`*

In case module vendor did not created a module in a normal way (no package type was specified or psr-4 autoloader prefix is different then module name), this config helps to watch for such packages. 
Key of the array is a package name. The value is a list of module names that the package brings.

**custom_module_pattern**

*Default: `./app/code/*/*/registration.php`*

A glob() pattern that helps to find custom non-composer magento modules.

### 🛠 MagentoLogNotification

It's useful to be notified when you have recently added records in Magento logs. This tasks checks log files located 
under `log_patterns` and informs if there are logs that have been added inside of time frame defined in `record_stale_threshold`.
The `exclude_severities` helps to reduce noisy records. 

```yaml
parameters:
    tasks:
        magento2-log-notification:
            log_patterns:
              - "./var/*/*.log"
            record_stale_threshold: "1" # in days
            exclude_severities:
              - "INFO"
              - "DEBUG"
```

**log_patterns**

*Default: `./var/*/*.log`*

Paths where log files should be watched

**record_stale_threshold**

*Default: `1`*

Stale threshold (in days) that helps to ignore old records in logs.

**exclude_severities**

*Default: `INFO, DEBUG`*

This config excludes records with specified severity levels.