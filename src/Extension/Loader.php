<?php

namespace Glushko\GrumphpMagento2\Extension;

use Glushko\GrumphpMagento2\Task\MagentoModuleRegistrationTask;
use Glushko\GrumphpMagento2\Task\MagentoLogNotificationTask;
use GrumPHP\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class Loader
 */
class Loader implements ExtensionInterface
{
    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function load(ContainerBuilder $container)
    {
        $container->register('magento2.new-module', MagentoModuleRegistrationTask::class)
            ->addArgument(new Reference('config'))
            ->addArgument(new Reference('process_builder'))
            ->addArgument(new Reference('formatter.raw_process'))
            ->addTag('grumphp.task', ['config' => 'magento2-module-registration']);

        $container->register('magento2.log-watcher', MagentoLogNotificationTask::class)
            ->addArgument(new Reference('config'))
            ->addArgument(new Reference('process_builder'))
            ->addArgument(new Reference('formatter.raw_process'))
            ->addTag('grumphp.task', ['config' => 'magento2-log-notification']);
    }
}
