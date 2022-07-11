<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\ORM;


use DevLancer\ORM\Exception\BadEntityException;
use PDO;
use PDOStatement;

/**
 *
 */
abstract class DatabaseManager
{
    use ColumnToPropertyTrait;

    /**
     * @var PDOStatement|null
     */
    private $PDOStatement = null;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var string
     */
    protected $sql = "";

    /**
     * @var EntityManager
     */
    protected $manager;

    /**
     * @param EntityManager $manager
     * @param string $entity
     */
    public function __construct(EntityManager $manager, string $entity)
    {
        $this->manager = $manager;
        $this->entity = $entity;

        $metadataFactory = $this->manager->getClassMetadata($entity);
        $this->table = $metadataFactory['class']['table'];
    }

    /**
     * @param string $sql
     * @return void
     */
    public function setSql(string $sql)
    {
        $this->sql = $sql;
    }

    /**
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @param string $name
     * @param $value
     * @return $this
     */
    public function setParameter(string $name, $value): self
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function execute(): bool
    {
        $this->generate();
        $sql = $this->getSql();
        $pdo = $this->manager->getPdo();
        $this->PDOStatement = $pdo->prepare($sql);
        return $this->PDOStatement->execute($this->parameters);
    }

    /**
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->manager->getPdo()->lastInsertId();
    }

    /**
     * @return mixed
     */
    abstract public function generate();

    /**
     * @return array
     * @throws BadEntityException
     */
    public function getResult(): array
    {
        $this->execute();
        $result = $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
        $entities = [];
        foreach ($result as $item) {
            $entities[] = $this->_createEntity($item);
        }

        return $entities;
    }

    /**
     * @param array $fetch
     * @return object
     * @throws BadEntityException
     */
    protected function _createEntity(array $fetch)
    {
        $metadataFactory = $this->manager->getClassMetadata($this->entity)['properties'];
        $entity = new $this->entity();
        Container::add($entity);

        foreach ($metadataFactory as $property) {
            $column = $property['column'];
            $type = $property['type'];
            foreach ($fetch as $col => $val) {
                if ($col === $column) {
                    if (!($setter = $this->getSetterPropertyFromColumn($entity, $column)))
                        throw new BadEntityException(sprintf("Property or setter method not found for %s column in entity %s", $column, (is_string($entity)? $entity : get_class($entity))));

                    $entity->{$setter}($this->convertDataType($val, $type));
                }
            }
        }

        return $entity;
    }

    /**
     * @param string $orig
     * @return string
     */
    protected function _translateNativeType(string $orig) {
        $trans = array(
            'VAR_STRING' => 'string',
            'STRING' => 'string',
            'BLOB' => 'string',
            'LONGLONG' => 'int',
            'LONG' => 'int',
            'SHORT' => 'int',
            'DATETIME' => 'string',
            'DATE' => 'string',
            'DOUBLE' => 'float',
            'TIMESTAMP' => 'int',
            'NEWDECIMAL' => 'float'
        );
        return $trans[$orig];
    }

    /**
     * @param $value
     * @param string $type
     * @return float|int|string|json|array
     */
    public function convertDataType($value, string $type)
    {
        if (!in_array(strtolower($type), ['int', 'string', 'float', 'json'])) {
            $type = $this->_translateNativeType($type);
        }

        switch ($type) {
            case "int":
                $value = (int) $value;
                break;
            case "float":
                $value = (float) $value;
                break;
            case "json":
                if (is_array($value))
                    $value = json_encode($value);
                else
                    $value = json_decode($value, true);
                break;
            default:
                $value = (string) $value;
                break;
        }

        return $value;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return PDOStatement|null
     */
    public function getPDOStatement(): PDOStatement
    {
        return $this->PDOStatement;
    }
}