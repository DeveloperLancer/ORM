<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\ORM\Query;


/**
 *
 */
class UpdateBuilder extends Builder
{
    /**
     * @var array
     */
    protected $updateParams = [];

    /**
     * @return void
     */
    public function generate()
    {
        $sql = sprintf("UPDATE %s SET %s %s", $this->table, $this->_generateUpdate(), $this->_generateWhere());
        $this->setSql($sql);
    }

    /**
     * @param string $name
     * @param $value
     * @return void
     */
    public function updateParameter(string $name, $value)
    {
        $this->updateParams[$name] = $value;
        $this->setParameter($name, $value);
    }

    /**
     * @return string
     */
    protected function _generateUpdate(): string
    {
        $result = [];

        foreach ($this->updateParams as $column => $value) {
            $result[] = sprintf('%s = :%s', $column, $column);
        }

        return implode(", ", $result);
    }
}