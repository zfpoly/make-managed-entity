<?= "<?php\n"; ?>

namespace <?= $namespace; ?>;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\ManagerRegistry;

abstract class AbstractManagedEntityRepository extends ServiceEntityRepository implements RepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, $this->getRepositoryEntityClassName());
    }
}
