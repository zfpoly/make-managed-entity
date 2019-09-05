<?php

namespace Bemila\Bundle\ManagerMakerBundle\Maker;

use Bemila\Bundle\ManagerMakerBundle\Doctrine\DoctrineHelperExtension;
use Bemila\Bundle\ManagerMakerBundle\Doctrine\EntityClassGeneratorExtension;
use Bemila\Bundle\ManagerMakerBundle\FileManagerExtension;
use Bemila\Bundle\ManagerMakerBundle\GeneratorExtension;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRelation;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Maker\MakeEntity;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MakeManagedEntity extends AbstractMaker implements MakerInterface
{
    /** @var \Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper */
    private $doctrineHelper;

    /** @var FileManagerExtension */
    private $fileManager;

    /** @var GeneratorExtension */
    private $generator;

    /** @var MakeEntity */
    private $makeEntity;

    /**
     * MakeEntityManagerCommand constructor.
     *
     * @param DoctrineHelperExtension $doctrineHelperExtension
     * @param FileManagerExtension    $fileManager
     * @param GeneratorExtension      $generator
     */
    public function __construct(
        DoctrineHelperExtension $doctrineHelperExtension,
        FileManagerExtension $fileManager,
        GeneratorExtension $generator,
        string $projectDirectory
    ) {
        $this->doctrineHelper = $doctrineHelperExtension->getDoctrineHelper();
        $this->fileManager = $fileManager;
        $this->generator = $generator;
        $this->makeEntity = new MakeEntity(
            $fileManager,
            $this->doctrineHelper,
            $projectDirectory,
            $this->generator
        );
    }

    /**
     * Return the command name for your maker (e.g. make:report).
     *
     * @return string
     */
    public static function getCommandName(): string
    {
        return 'make:managed-entity';
    }

    /**
     * Configure the command: set description, input arguments, options, etc.
     *
     * By default, all arguments will be asked interactively. If you want
     * to avoid that, use the $inputConfig->setArgumentAsNonInteractive() method.
     *
     * @param Command            $command
     * @param InputConfiguration $inputConfig
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        $this->makeEntity->configureCommand($command, $inputConfig);
        $command->setDescription(
            'Creates or updates a Doctrine entity class with a Manager to handle its crud operations,
  and optionally an API Platform resource.'
        )->setHelp(file_get_contents(__DIR__.'/../Resources/help/MakeManagedEntity.txt'));
    }

    /**
     * {@inheritdoc}
     */
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        $this->makeEntity->interact($input, $io, $command);
    }

    /**
     * Configure any library dependencies that your maker requires.
     *
     * @param DependencyBuilder $dependencies
     */
    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $this->makeEntity->configureDependencies($dependencies);
    }

    /**
     * @param InputInterface $input
     * @param ConsoleStyle   $io
     * @param Generator      $generator
     *
     * @throws \Exception
     */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        $overwrite = $input->getOption('overwrite');

        // the regenerate option has entirely custom behavior
        if ($input->getOption('regenerate')) {
            $this->regenerateEntities($input->getArgument('name'), $overwrite, $this->generator);
            $this->writeSuccessMessage($io);

            return;
        }

        /** @var ClassNameDetails $entityClassDetails */
        $entityClassDetails = $this->generator->createClassNameDetails(
            $input->getArgument('name'),
            'Entity\\'
        );

        $classExists = class_exists($entityClassDetails->getFullName());
        if (!$classExists) {
            $entityClassGenerator = new EntityClassGeneratorExtension($this->generator);
            $entityPath = $entityClassGenerator->generateEntityClass(
                $entityClassDetails,
                $input->getOption('api-resource')
            );

            $this->generator->writeChanges();
        }

        if (!$this->doesEntityUseAnnotationMapping($entityClassDetails->getFullName())) {
            throw new RuntimeCommandException(
                sprintf(
                    'Only annotation mapping is supported by make:entity, but the <info>%s</info> 
class uses a different format. If you would like this command to generate the properties & getter/setter methods, 
add your mapping configuration, and then re-run this command with the <info>--regenerate</info> flag.',
                    $entityClassDetails->getFullName()
                )
            );
        }

        if ($classExists) {
            $entityPath = $this->getPathOfClass($entityClassDetails->getFullName());
            $io->text(
                [
                    'Your entity already exists! So let\'s add some new fields!',
                ]
            );
        } else {
            $io->text(
                [
                    '',
                    'Entity generated! Now let\'s add some fields!',
                    'You can always add more fields later manually or by re-running this command.',
                ]
            );
        }

        $currentFields = $this->getPropertyNames($entityClassDetails->getFullName());
        $manipulator = $this->createClassManipulator($entityPath, $io, $overwrite);

        $isFirstField = true;
        while (true) {
            $newField = $this->askForNextField($io, $currentFields, $entityClassDetails->getFullName(), $isFirstField);
            $isFirstField = false;

            if (null === $newField) {
                break;
            }

            $fileManagerOperations = [];
            $fileManagerOperations[$entityPath] = $manipulator;

            if (\is_array($newField)) {
                $annotationOptions = $newField;
                unset($annotationOptions['fieldName']);
                $manipulator->addEntityField($newField['fieldName'], $annotationOptions);

                $currentFields[] = $newField['fieldName'];
            } elseif ($newField instanceof EntityRelation) {
                // both overridden below for OneToMany
                $newFieldName = $newField->getOwningProperty();
                if ($newField->isSelfReferencing()) {
                    $otherManipulatorFilename = $entityPath;
                    $otherManipulator = $manipulator;
                } else {
                    $otherManipulatorFilename = $this->getPathOfClass($newField->getInverseClass());
                    $otherManipulator = $this->createClassManipulator($otherManipulatorFilename, $io, $overwrite);
                }
                switch ($newField->getType()) {
                    case EntityRelation::MANY_TO_ONE:
                        if ($newField->getOwningClass() === $entityClassDetails->getFullName()) {
                            // THIS class will receive the ManyToOne
                            $manipulator->addManyToOneRelation($newField->getOwningRelation());

                            if ($newField->getMapInverseRelation()) {
                                $otherManipulator->addOneToManyRelation($newField->getInverseRelation());
                            }
                        } else {
                            // the new field being added to THIS entity is the inverse
                            $newFieldName = $newField->getInverseProperty();
                            $otherManipulatorFilename = $this->getPathOfClass($newField->getOwningClass());
                            $otherManipulator =
                                $this->createClassManipulator($otherManipulatorFilename, $io, $overwrite);

                            // The *other* class will receive the ManyToOne
                            $otherManipulator->addManyToOneRelation($newField->getOwningRelation());
                            if (!$newField->getMapInverseRelation()) {
                                throw new \Exception(
                                    'Somehow a OneToMany relationship is being created, 
                                    but the inverse side will not be mapped?'
                                );
                            }
                            $manipulator->addOneToManyRelation($newField->getInverseRelation());
                        }

                        break;
                    case EntityRelation::MANY_TO_MANY:
                        $manipulator->addManyToManyRelation($newField->getOwningRelation());
                        if ($newField->getMapInverseRelation()) {
                            $otherManipulator->addManyToManyRelation($newField->getInverseRelation());
                        }

                        break;
                    case EntityRelation::ONE_TO_ONE:
                        $manipulator->addOneToOneRelation($newField->getOwningRelation());
                        if ($newField->getMapInverseRelation()) {
                            $otherManipulator->addOneToOneRelation($newField->getInverseRelation());
                        }

                        break;
                    default:
                        throw new \Exception('Invalid relation type');
                }

                // save the inverse side if it's being mapped
                if ($newField->getMapInverseRelation()) {
                    $fileManagerOperations[$otherManipulatorFilename] = $otherManipulator;
                }
                $currentFields[] = $newFieldName;
            } else {
                throw new \Exception('Invalid value');
            }

            foreach ($fileManagerOperations as $path => $manipulatorOrMessage) {
                if (\is_string($manipulatorOrMessage)) {
                    $io->comment($manipulatorOrMessage);
                } else {
                    $this->fileManager->dumpFile($path, $manipulatorOrMessage->getSourceCode());
                }
            }
        }

        $this->writeSuccessMessage($io);
        $io->text(
            [
                'Next: When you\'re ready, create a migration with <comment>make:migration</comment>',
                '',
            ]
        );
    }

    /**
     * @param ConsoleStyle $io
     * @param array        $fields
     * @param string       $entityClass
     * @param bool         $isFirstField
     *
     * @return array|EntityRelation
     */
    private function askForNextField(ConsoleStyle $io, array $fields, string $entityClass, bool $isFirstField)
    {
        $reflectionObject = new \ReflectionObject($this->makeEntity);
        $method = $reflectionObject->getMethod('askForNextField');
        $method->setAccessible(true);

        return $method->invokeArgs($this->makeEntity, [$io, $fields, $entityClass, $isFirstField]);
    }

    /**
     * @param string       $path
     * @param ConsoleStyle $io
     * @param bool         $overwrite
     *
     * @return ClassSourceManipulator
     */
    private function createClassManipulator(string $path, ConsoleStyle $io, bool $overwrite): ClassSourceManipulator
    {
        $reflectionObject = new \ReflectionObject($this->makeEntity);
        $method = $reflectionObject->getMethod('createClassManipulator');
        $method->setAccessible(true);

        return $method->invokeArgs($this->makeEntity, [$path, $io, $overwrite]);
    }

    /**
     * @param string $class
     *
     * @return string
     */
    private function getPathOfClass(string $class): string
    {
        $reflectionObject = new \ReflectionObject($this->makeEntity);
        $method = $reflectionObject->getMethod('getPathOfClass');
        $method->setAccessible(true);

        return $method->invokeArgs($this->makeEntity, [$class]);
    }

    /**
     * @param string    $classOrNamespace
     * @param bool      $overwrite
     * @param Generator $generator
     */
    private function regenerateEntities(string $classOrNamespace, bool $overwrite, Generator $generator)
    {
        $reflectionObject = new \ReflectionObject($this->makeEntity);
        $method = $reflectionObject->getMethod('regenerateEntities');
        $method->setAccessible(true);

        $method->invokeArgs($this->makeEntity, [$classOrNamespace, $overwrite, $generator]);
    }

    /**
     * @param string $class
     *
     * @return array
     */
    private function getPropertyNames(string $class): array
    {
        $reflectionObject = new \ReflectionObject($this->makeEntity);
        $method = $reflectionObject->getMethod('getPropertyNames');
        $method->setAccessible(true);

        return $method->invokeArgs($this->makeEntity, [$class]);
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    private function doesEntityUseAnnotationMapping(string $className): bool
    {
        $reflectionObject = new \ReflectionObject($this->makeEntity);
        $method = $reflectionObject->getMethod('doesEntityUseAnnotationMapping');
        $method->setAccessible(true);

        return $method->invokeArgs($this->makeEntity, [$className]);
    }
}
