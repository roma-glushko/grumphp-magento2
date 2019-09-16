<?php

namespace Glushko\GrumphpMagento2\Extension;

use Glushko\GrumphpMagento2\Task\MagentoNewModuleTask;
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
     */
    public function load(ContainerBuilder $container)
    {
        $container->register('magento2.new-module', MagentoNewModuleTask::class)
            ->addArgument(new Reference('config'))
            ->addArgument(new Reference('process_builder'))
            ->addTag('grumphp.task', ['config' => 'magento2-new-module']);
    }
}
