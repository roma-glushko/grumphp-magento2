<?php

namespace Glushko\GrumphpMagento2\Task;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\AbstractExternalTask;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * MagentoModuleRegistrationTask task
 */
class MagentoModuleRegistrationTask extends AbstractExternalTask
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'magento2-module-registration';
    }

    /**
     * @return OptionsResolver
     */
    public function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        $resolver->setDefaults([
            'composer_json_path' => './composer.json',
            'composer_home_path' => './var/composer_home',
            'configphp_path' => './app/etc/config.php',
            'allowed_package_types' => ['magento2-module', 'magento2-component'],
            'allowed_packages' => [
                'magento/data-migration-tool' => ['Magento_DataMigrationTool'],
            ],
            'custom_module_pattern' => './app/code/*/*/registration.php',
        ]);

        $resolver->addAllowedTypes('allowed_package_types', ['array']);
        $resolver->addAllowedTypes('allowed_packages', ['array']);
        $resolver->addAllowedTypes('custom_module_pattern', ['string']);
        $resolver->addAllowedTypes('composer_json_path', ['string']);
        $resolver->addAllowedTypes('composer_home_path', ['string']);
        $resolver->addAllowedTypes('configphp_path', ['string']);

        return $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function canRunInContext(ContextInterface $context): bool
    {
        return ($context instanceof GitPreCommitContext || $context instanceof RunContext);
    }

    /**
     * {@inheritdoc}
     */
    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfiguration();
        $customModulePattern = $config['custom_module_pattern'];
        $allowedPackageTypes = $config['allowed_package_types'];
        $allowedPackages = $config['allowed_packages'];
        $composerJsonPath = $config['composer_json_path'];
        $composerHomePath = $config['composer_home_path'];
        $configPhpPath = $config['configphp_path'];

        // todo: need to support GitPreCommitContext and RunContext contexts

        $composerModules = $this->getComposerModules(
            $composerJsonPath,
            $composerHomePath,
            $allowedPackageTypes,
            $allowedPackages
        );

        $customModules = $this->getCustomMagentoModules($customModulePattern);
        $declaredModules = $this->getDeclaredModuleList($configPhpPath);

        $allModules = array_unique(array_merge($composerModules, $customModules));
        $unregisteredModules = array_diff($allModules, $declaredModules);
        $notExistedModules = array_diff($declaredModules, $allModules);

        $errorMessage = '';

        if (count($unregisteredModules) > 0) {
            $errorMessage = '✘ Modules that were not registered in config.php:' .
                PHP_EOL .
                implode(PHP_EOL, $unregisteredModules);
        }

        if (count($notExistedModules) > 0) {
            $errorMessage .= PHP_EOL . PHP_EOL .
                '✘ Modules that doesnt exist but registered in config.php:' .
                PHP_EOL .
                implode(PHP_EOL, $notExistedModules);
        }

        if ($errorMessage !== '') {
            return TaskResult::createFailed(
                $this,
                $context,
                $errorMessage
            );
        }

        return TaskResult::createPassed($this, $context);
    }

    /**
     * @return array
     */
    private function getCustomMagentoModules(string $customModulePattern): array
    {
        $files = glob($customModulePattern, GLOB_NOSORT);

        if ($files === false) {
            throw new RuntimeException("glob() doesn't retrieve custom modules");
        }

        $pathPartToRemove = explode('*/*', $customModulePattern);

        $magentoModules = array_map(function ($file) use ($pathPartToRemove) {
            $autoloaderPrefix = str_replace($pathPartToRemove, '', $file);

            return str_replace('/', '_', trim($autoloaderPrefix, '\\'));
        }, $files);

        return $magentoModules;
    }

    /**
     * @return array
     */
    private function getDeclaredModuleList(string $configPhpPath): array
    {
        if (!file_exists($configPhpPath)) {
            throw new RuntimeException("config.php file doesn't exist");
        }

        $magentoConfig = require $configPhpPath;

        return array_key_exists('modules', $magentoConfig) ? array_keys($magentoConfig['modules']) : [];
    }

    /**
     * @return Composer
     */
    private function getComposer(string $composerJsonPath, string $composerHomePath): Composer
    {
        putenv('COMPOSER_HOME=' . $composerHomePath);

        return ComposerFactory::create(
            new BufferIO(),
            $composerJsonPath
        );
    }

    /**
     * @param string $composerJsonPath
     * @param string $composerHomePath
     * @param array $allowedPackageTypes
     * @param array $allowedPackages
     *
     * @return array
     */
    public function getComposerModules(
        string $composerJsonPath,
        string $composerHomePath,
        array $allowedPackageTypes,
        array $allowedPackages
    ): array {
        $packages = [];

        $composerLocker = $this->getComposer($composerJsonPath, $composerHomePath)->getLocker();

        /** @var CompletePackageInterface $package */
        foreach ($composerLocker->getLockedRepository()->getPackages() as $package) {
            $packageName = $package->getPrettyName();
            $packageType = $package->getType();

            if (!(in_array($packageType, $allowedPackageTypes) || array_key_exists($packageName, $allowedPackages))
                || $this->isSystemPackage($packageName)
            ) {
                continue;
            }

            // process exceptions that declared in abnormal way
            if (array_key_exists($packageName, $allowedPackages)) {
                $moduleNames = $allowedPackages[$packageName];
                $packages = array_merge($packages, $moduleNames);

                continue;
            }

            // process normal magento packages
            $autoload = $package->getAutoload();

            if (!array_key_exists('psr-4', $autoload) || count($autoload['psr-4']) == 0) {
                continue;
            }

            $moduleNames = array_map(function ($autoloaderPrefix) {
                return str_replace('\\', '_', trim($autoloaderPrefix, '\\'));
            }, array_keys($autoload['psr-4']));

            $packages = array_merge($packages, $moduleNames);
        }

        return $packages;
    }

    /**
     * Checks if the passed packaged is system package
     *
     * @param string $packageName
     *
     * @return bool
     */
    public function isSystemPackage($packageName = '')
    {
        return preg_match('/magento\/product-*/', $packageName) == 1;
    }
}
