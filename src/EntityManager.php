<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\ORM;


use DevLancer\ORM\Exception\BadEntityException;
use DevLancer\ORM\Exception\NotFoundRepositoryException;
use DevLancer\ORM\Query\DeleteBuilder;
use DevLancer\ORM\Query\InsertBuilder;
use DevLancer\ORM\Query\SelectBuilder;
use DevLancer\ORM\Query\UpdateBuilder;
use PDO;

/**
 *
 */
class EntityManager
{
    use ColumnToPropertyTrait;

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var ClassMetadata
     */
    private $metadataFactory;

    /**
     * @var array
     */
    private $deleteEvent = [];

    /**
     * @var array
     */
    private $insertEvent = [];

    /**
     * @param PDO $pdo
     * @param ClassMetadata $metadataFactory
     */
    public function __construct(PDO $pdo, ClassMetadata $metadataFactory)
    {
        $this->pdo = $pdo;
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param string $className
     * @return array
     */
    public function getClassMetadata(string $className): array
    {
        return [
            'class' => $this->metadataFactory->getReflectionClass($className),
            'properties' => $this->metadataFactory->getReflectionProperty($className)
        ];
    }

    /**
     * @throws BadEntityException
     * @throws NotFoundRepositoryException
     */
    public function getRepository(string $entity): Repository
    {
        $metadataFactory = $this->getClassMetadata($entity);
        $metadataFactoryClass = $metadataFactory['class'];

        if (!isset($metadataFactoryClass['table'])) {
            throw new BadEntityException("Not found table annotation (@ORMLite\Table({\"name-table\"}))");
        }

        $repository = $metadataFactoryClass['repository'] ?? Repository::class;

        if (!class_exists($repository)) {
            throw new NotFoundRepositoryException(sprintf("Not found %s repository", $repository));
        }

        return new $repository($this, $entity);
    }

    /**
     * @throws BadEntityException
     * @throws NotFoundRepositoryException
     * @return object|null
     */
    public function find($entityName, $id)
    {
        return $this->getRepository($entityName)->find($id);
    }

    /**
     * @return void
     */
    public function clear()
    {
        Container::reset();
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove($entity)
    {
        $this->deleteEvent[] = $entity;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function persist($entity)
    {
        $this->insertEvent[] = $entity;
    }

    /**
     * @return void
     * @throws BadEntityException
     */
    public function flush()
    {
        //todo id nie moze miec typu json bo nie jest castowane
        foreach ($this->insertEvent as $index => $entity) {
            unset($this->insertEvent[$index]);
            $metadataFactory = $this->getClassMetadata(get_class($entity));
            $columnId = $metadataFactory['class']['id'];
            $insertBuilder = new InsertBuilder($this, get_class($entity));
            foreach ($metadataFactory['properties'] as $property) {
                if (isset($columnId[0]) && $property['column'] === $columnId[0]) {
                    continue;
                }

                if (!($getter = $this->getGetterPropertyFromColumn($entity, $property['column'])))
                    throw new BadEntityException(sprintf("Property or getter method not found for %s column in entity %s", $property['column'], (is_string($entity)? $entity : get_class($entity))));

                $value = $insertBuilder->convertDataType($entity->{$getter}(), $property['type']);
                $insertBuilder->setParameter($property['column'], $value);
            }

            $insertBuilder->execute();
            $errorInfo = $insertBuilder->getPDOStatement()->errorInfo();
            if ($errorInfo[0] !== "00000") {
                trigger_error(sprintf("SQLSTATE [%s] [%s] %s", $errorInfo[0], $errorInfo[1], $errorInfo[2]), E_USER_WARNING);
            }
            if (isset($columnId[0])) {
                $id = $insertBuilder->lastInsertId();

                if (!($setter = $this->getSetterPropertyFromColumn($entity, $columnId[0])))
                    throw new BadEntityException(sprintf("Property or setter method not found for %s column in entity %s", $columnId[0], (is_string($entity)? $entity : get_class($entity))));

                $value = $insertBuilder->convertDataType($id, $columnId[1]);
                $entity->{$setter}($value);
            }
        }

        foreach ($this->deleteEvent as $index => $entity) {
            $deleteBuilder = new DeleteBuilder($this, get_class($entity));
            $metadataFactory = $this->getClassMetadata(get_class($entity));
            $columnId = $metadataFactory['class']['id'];
            if ($columnId === null) {
                trigger_error(sprintf("The %s entity cannot be automatically deleted because it does not have an identifier (primary key) defined.", get_class($entity)));
                continue;
            }

            if (!($getter = $this->getGetterPropertyFromColumn($entity, $columnId[0])))
                throw new BadEntityException(sprintf("Property or getter method not found for %s column in entity %s", $columnId[0], (is_string($entity)? $entity : get_class($entity))));
            $id = $entity->{$getter}();

            $deleteBuilder
                ->where(sprintf('%s = :%s', $columnId[0], $columnId[0]))
                ->setParameter($columnId[0], $id);


            $deleteBuilder->execute();
            $errorInfo = $deleteBuilder->getPDOStatement()->errorInfo();
            if ($errorInfo[0] !== "00000") {
                trigger_error(sprintf("SQLSTATE [%s] [%s] %s", $errorInfo[0], $errorInfo[1], $errorInfo[2]), E_USER_WARNING);
            }
            Container::remove($entity);
            unset($this->deleteEvent[$index]);
        }

        $updateEvent = Container::getContainer();
        foreach ($updateEvent as $entityData) {
            $entity = $entityData[0];
            $hash = $entityData[1];
            if (Container::checkHash($entity, $hash))
                continue;

            $metadataFactory = $this->getClassMetadata(get_class($entity));
            $updateBuilder = new UpdateBuilder($this, get_class($entity));
            $columnId = $metadataFactory['class']['id'];
            if ($columnId === null) {
                trigger_error(sprintf("The %s entity cannot be automatically updated because it does not have an identifier (primary key) defined.", get_class($entity)));
                continue;
            }


            if (!($getter = $this->getGetterPropertyFromColumn($entity, $columnId[0])))
                throw new BadEntityException(sprintf("Property or getter method not found for %s in entity %s", $columnId[0], (is_string($entity)? $entity : get_class($entity))));

            $id = $entity->{$getter}();
            $updateBuilder
                ->where(sprintf('%s = :%s', $columnId[0], $columnId[0]))
                ->setParameter($columnId[0], $id);

            foreach ($metadataFactory['properties'] as $property) {
                if (!($getter = $this->getGetterPropertyFromColumn($entity, $property['column'])))
                    throw new BadEntityException(sprintf("Property or getter method not found for %s in entity %s", $property['column'], (is_string($entity)? $entity : get_class($entity))));

                $value = $updateBuilder->convertDataType($entity->{$getter}(), $property['type']);
                $updateBuilder
                    ->updateParameter($property['column'], $value);
            }

            $updateBuilder->execute();
            $errorInfo = $updateBuilder->getPDOStatement()->errorInfo();
            if ($errorInfo[0] !== "00000") {
                trigger_error(sprintf("SQLSTATE [%s] [%s] %s", $errorInfo[0], $errorInfo[1], $errorInfo[2]), E_USER_WARNING);
            }
        }
    }

    /**
     * @param string $entity
     * @return SelectBuilder
     */
    public function createQueryBuilder(string $entity): SelectBuilder
    {
        return new SelectBuilder($this, $entity);
    }

    /**
     * @param string $entity
     * @param string $sql
     * @return Query
     */
    public function createQuery(string $entity, string $sql): Query
    {
        $query = new Query($this, $entity);
        $query->setSql($sql);

        return $query;
    }

}