<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\ORM;


/**
 *
 */
class Container
{
    /**
     * @var array
     */
    private static $container = [];

    /**
     * @return void
     */
    public static function reset()
    {
        self::$container = [];
    }

    /**
     * @param $value
     * @return void
     */
    public static function add($value)
    {
        $hash = hash("sha256", json_encode($value));
        self::$container[] = [$value, $hash];
    }

    /**
     * @param $value
     * @return void
     */
    public static function remove($value)
    {
        foreach (self::$container as $index => $item) {
            if ($item[0] === $value) {
                unset(self::$container[$index]);
            }
        }
    }

    /**
     * @return array
     */
    public static function getContainer(): array
    {
        return self::$container;
    }

    /**
     * @param object $value
     * @param bool $strict
     * @return array
     */
    public static function search($value, bool $strict = false): array
    {
        $result = [];

        foreach (self::getContainer() as $key => $item) {
            if ($strict) {
                if ($item === $value)
                    $result[] = $key;
            } else {
                if ($item == $value)
                    $result[] = $key;
            }
        }

        return $result;
    }


    /**
     * @param object $value
     * @param string $hash
     * @return bool
     */
    public static function checkHash($value, string $hash): bool
    {
        return (bool) hash("sha256", json_encode($value)) == $hash;
    }
}