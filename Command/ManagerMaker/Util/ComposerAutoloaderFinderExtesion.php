<?php

namespace App\Command\ManagerMaker\Util;

use Composer\Autoload\ClassLoader;
use Symfony\Bundle\MakerBundle\Util\ComposerAutoloaderFinder;

class ComposerAutoloaderFinderExtesion extends ComposerAutoloaderFinder
{
    /**
     * ComposerAutoloaderFinderExtesion constructor.
     */
    public function __construct()
    {
        parent::__construct('App\\');
    }
}
