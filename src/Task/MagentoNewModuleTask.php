<?php

namespace Glushko\GrumphpMagento2\Task;

use Composer\Factory as ComposerFactory;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Locker as ComposerLocker;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use GrumPHP\Task\AbstractExternalTask;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * MagentoNewModuleTask task
 */
class MagentoNewModuleTask extends AbstractExternalTask
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'magento2-new-module';
    }

    /**
     * @return OptionsResolver
     */
    public function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        // todo: implement

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
        // todo: implement

        return TaskResult::createPassed($this, $context);
    }

    /**
     * @return
     */
    private function getComposer()
    {
        putenv('COMPOSER_HOME=' . './var/composer_home'); //todo: move to configs

        return ComposerFactory::create(
            new BufferIO(),
            './composer.json'  //todo: move to configs
        );
    }

    /**
     * @return array
     */
    public function getInstalledMagentoPackages()
    {
        $packages = [];

        /** @var CompletePackageInterface $package */
        foreach ($this->getLocker()->getLockedRepository()->getPackages() as $package) {
            if ((in_array($package->getType(), ['magento2-module']))
                && (!$this->isSystemPackage($package->getPrettyName()))) {
                $packages[$package->getName()] = [
                    'name' => $package->getName(),
                    'type' => $package->getType(),
                    'version' => $package->getPrettyVersion()
                ];
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
        if (preg_match('/magento\/product-*/', $packageName) == 1) {
            return true;
        }

        return false;
    }

    /**
     * Load locker
     *
     * @return ComposerLocker
     */
    private function getLocker()
    {
        return $this->getComposer()->getLocker();
    }
}
