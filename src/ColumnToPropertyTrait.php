<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\ORM;

trait ColumnToPropertyTrait
{
    protected function columnToProperty(string $column): string
    {
        $property = explode("_", $column);
        $first = false;
        foreach ($property as &$item) {
            if (!$first) {
                $first = true;
                continue;
            }

            $item = ucfirst($item);
        }

        return implode("",$property);
    }

    protected function getGetterPropertyFromColumn($entity, string $column)
    {
        $property = $this->columnToProperty($column);
        if (!property_exists($entity, $property)) {
            return null;
        }

        $getter = "get" . ucfirst($property);
        if (!method_exists($entity, $getter))
            return null;

        return $getter;
    }

    protected function getSetterPropertyFromColumn($entity, string $column)
    {
        $property = $this->columnToProperty($column);
        if (!property_exists($entity, $property)) {
            return null;
        }

        $getter = "set" . ucfirst($property);
        if (!method_exists($entity, $getter))
            return null;

        return $getter;
    }
}