<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\ORM\Query;


use DevLancer\ORM\DatabaseManager;

/**
 *
 */
abstract class Builder extends DatabaseManager
{

    /**
     * @var array
     */
    protected $where = [];

    /**
     * @param string $column
     * @return $this
     */
    public function where(string $column): self
    {
        $this->where[] = ['type' => "AND", 'val' => $column];

        return $this;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function andWhere(string $column): self
    {
        $this->where($column);
        return $this;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function orWhere(string $column): self
    {
        $this->where[] = ['type' => "OR", 'val' => $column];
        return $this;
    }

    /**
     * @return string
     */
    protected function _generateWhere(): string
    {
        $where = "";

        if ($this->where === []) {
            $where = "WHERE 1";
        } else {
            foreach ($this->where as $item) {
                if ($where === "") {
                    $where = $item['val'];
                } else {
                    $where = sprintf("(%s) %s (%s)", $where, $item['type'], $item['val']);
                }
            }
            $where = "WHERE " . $where;
        }

        return $where;
    }
}