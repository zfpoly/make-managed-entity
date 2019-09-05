<?php

namespace Bemila\Bundle\ManagerMakerBundle;

use Bemila\Bundle\ManagerMakerBundle\Util\ComposerAutoloaderFinderExtesion;
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
        ComposerAutoloaderFinderExtesion $composerAutoloaderFinder,
        string $projectDirectory,
        string $defaultTemplateDirectory
    ) {
        parent::__construct(
            $fs,
            new AutoloaderUtil($composerAutoloaderFinder),
            $projectDirectory,
            $defaultTemplateDirectory
        );
    }
}
