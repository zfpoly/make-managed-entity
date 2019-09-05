<?php

namespace Bemila\Bundle\ManagerMakerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Bundle\MakerBundle\DependencyInjection\Configuration as MakerBundleConfiguration;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        return (new MakerBundleConfiguration())->getConfigTreeBuilder();
    }
}
