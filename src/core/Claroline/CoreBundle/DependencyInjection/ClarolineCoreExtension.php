<?php

namespace Claroline\CoreBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * Loads the core services configuration files.
 */
class ClarolineCoreExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $locator = new FileLocator(__DIR__ . '/../Resources/config/services');
        $loader = new YamlFileLoader($container, $locator);
        $loader->load('configuration.yml');
        $loader->load('browsing.yml');
        $loader->load('listeners.yml');
        $loader->load('installation.yml');
        $loader->load('security.yml');
        $loader->load('workspace.yml');
        $loader->load('resource.yml');
        $loader->load('utilities.yml');
        $loader->load('widget.yml');
        $loader->load('user.yml');
        $loader->load('validator.yml');
        $loader->load('home.yml');
    }
}
