<?php

namespace Bemila\Bundle\ManagerMakerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class ManagerMakerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
        $loader->load('makers.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $rootNamespace = trim($config['root_namespace'], '\\');
        $autoloaderFinderExtensionDefinition = $container->getDefinition('bemila.autoloader_finder_extension');
        $autoloaderFinderExtensionDefinition->replaceArgument(0, $rootNamespace);

        $generatorExtensionDefinition = $container->getDefinition('bemila.generator_extension');
        $generatorExtensionDefinition->replaceArgument(1, $rootNamespace);

        $doctrineHelperExtensionDefinition = $container->getDefinition('bemila.doctrine_helper_extension');
        $doctrineHelperExtensionDefinition->replaceArgument(0, $rootNamespace.'\\Entity');
    }
}