<?php

namespace App\Command\ManagerMaker;

use App\Command\ManagerMaker\Util\ComposerAutoloaderFinderExtesion;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class FileManagerExtension extends FileManager
{
    /**
     * FileManagerExtension constructor.
     *
     * @param Filesystem                       $fs
     * @param KernelInterface                  $kernel
     * @param ComposerAutoloaderFinderExtesion $composerAutoloaderFinder
     */
    public function __construct(
        Filesystem $fs,
        KernelInterface $kernel,
        ComposerAutoloaderFinderExtesion $composerAutoloaderFinder
    ) {
        parent::__construct(
            $fs,
            new AutoloaderUtil($composerAutoloaderFinder),
            $kernel->getProjectDir(),
            $kernel->getProjectDir() . '/templates'
        );
    }
}
