<?php

namespace Glushko\GrumphpMagento2\Task;

use Composer\Composer as Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Locker as ComposerLocker;
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
            'config_path' => './app/etc/config.php',
            'allowed_package_types' => ['magento2-module', 'magento2-component'],
            'custom_module_pattern' => './app/code/*/*/registration.php',
            'module_package_include_list' => ['magento/data-migration-tool'],
        ]);

        $resolver->addAllowedTypes('allowed_package_types', ['array']);
        $resolver->addAllowedTypes('module_package_include_list', ['array']);
        $resolver->addAllowedTypes('custom_module_pattern', ['string']);
        $resolver->addAllowedTypes('composer_json_path', ['string']);
        $resolver->addAllowedTypes('composer_home_path', ['string']);
        $resolver->addAllowedTypes('config_path', ['string']);

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
        $composerJsonPath = $config['composer_json_path'];
        $composerHomePath = $config['composer_home_path'];
        $configPhpPath = $config['config_path'];

        // todo: need to support GitPreCommitContext and RunContext contexts

        $magentoComposerModules = $this->getInstalledMagentoPackages(
            $composerJsonPath,
            $composerHomePath,
            $allowedPackageTypes
        );

        $magentoCustomModules = $this->getCustomMagentoModules($customModulePattern);
        $declaredModules = $this->getDeclaredModuleList($configPhpPath);

        // todo: need to implement whitelist of modules which doesn't declare package type

        var_dump(array_diff($declaredModules, $magentoComposerModules, $magentoCustomModules));

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

        $pathPartToRemove = explode('/*/*/', $customModulePattern);

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
     *
     * @return array
     */
    public function getInstalledMagentoPackages(
        string $composerJsonPath,
        string $composerHomePath,
        array $allowedPackageTypes
    ): array {
        $packages = [];

        $composerLocker = $this->getComposer($composerJsonPath, $composerHomePath)->getLocker();

        /** @var CompletePackageInterface $package */
        foreach ($composerLocker->getLockedRepository()->getPackages() as $package) {
            if ((in_array($package->getType(), $allowedPackageTypes))
                && (!$this->isSystemPackage($package->getPrettyName()))) {

                $autoload = $package->getAutoload();

                if (array_key_exists('psr-4', $autoload) && count($autoload['psr-4']) > 0) {
                    $moduleNames = array_map(function ($autoloaderPrefix) {
                        return str_replace('\\', '_', trim($autoloaderPrefix, '\\'));
                    }, array_keys($autoload['psr-4']));
                }

                $packages = array_merge($packages, $moduleNames);
            }
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
