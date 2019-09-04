<?php

namespace App\Command\ManagerMaker\Doctrine;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;

class DoctrineHelperExtension
{
    /**
     * @var DoctrineHelper
     */
    private $doctrineHelper;

    /**
     * DoctrineHelperExtension constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->doctrineHelper = new DoctrineHelper('App\Entity', $registry);
    }

    /**
     * @return DoctrineHelper
     */
    public function getDoctrineHelper(): DoctrineHelper
    {
        return $this->doctrineHelper;
    }
}
