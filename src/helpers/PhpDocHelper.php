<?php
namespace apiman\helpers;

/**
 * Работа с комментриями классов и методов
 * @package apiman\helpers
 */
class PhpDocHelper
{

    /**
     * Из php док вытаскиваем класс из атрибута @field
     * @param $className
     * @param $method
     * @return array|null
     * @throws \ReflectionException
     */
    public static function getReturnedInDocs($className, $method)
    {
        $docs = (new \ReflectionMethod($className, $method))->getDocComment();
        $lines = explode("\n", $docs);
        $isArray = false;
        foreach ($lines as $line) {
            if (mb_strpos($line, '@field') === false) {
                continue;
            }

            if (mb_strpos($line, '[]') !== false) {
                $isArray = true;
            }
            $line = str_replace(['@field', '*', ' ', '[]', "\r"], '', $line);
            $line = strtok($line, '|');
            return [$line, $isArray];
        }
        return null;
    }
}
