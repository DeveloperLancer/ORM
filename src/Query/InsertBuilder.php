<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\ORM\Query;


class InsertBuilder extends Builder
{

    public function generate()
    {
        $column = [];
        $params = [];

        foreach ($this->parameters as $col => $val) {
            $column[] = $col;
            $params[] = ":" . $col;
        }

        $sql = sprintf("INSERT INTO %s(%s) VALUES (%s)", $this->table, implode(", ", $column), implode(", ", $params));
        $this->setSql($sql);
    }

}