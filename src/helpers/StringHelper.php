<?php

namespace apiman\helpers;

use Yii;

/**
 * Class StringHelper
 * @package apiman\helpers
 */
class StringHelper extends \yii\helpers\StringHelper
{
    /**
     * константа пустой строки
     */
    const EMPTY_STR = '';

    /*
    $is_rus = StringHelper::isRus($this->name);
    $this->subname = StringHelper::translit($this->name, $is_rus); // = Vertera
    $this->subname .= ',' . StringHelper::keyboard($this->subname, !$is_rus) . ','; // = Vertera,Мукеукф,
    $this->subname .= StringHelper::keyboard($this->name, $is_rus); // = Vertera,Мукеукф,Dthnthf
    */

    /**
     * Получаем из строки число
     * @param string $str
     * @return null|string|string[]
     */
    public static function stringToInt(string $str)
    {
        return (int)preg_replace('/[^0-9]+/isU', '', $str);
    }

    /**
     *  Переводим строку в верхний регистр
     * @param string|null $str
     * @return mixed|null|string|string[]
     */
    public static function toUpper(string $str = null)
    {
        if ($str) {
            return mb_strtoupper($str);
        }
        return null;
    }

    /**
     * Первый символ с большой буквы
     * @param string $str
     * @return mixed|null|string|string[]
     */
    public static function mbConvertCase(string $str)
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }


    /**
     *  Переводим строку в нижний регистр
     * @param string $str
     * @return mixed|string
     */
    public static function toLowwer(string $str)
    {
        return mb_strtolower($str);
    }

    /**
     * обрезается строка слева от первого разделителя $delimiter
     * @param $string
     * @param $delimiter
     * @return string
     */
    public static function cutLeftPart($string, $delimiter = '.')
    {
        $part = explode($delimiter, $string);
        array_shift($part);

        return implode($delimiter, $part);
    }

    /**
     * обрезается строка справа от первого разделителя $delimiter
     * @param $string
     * @param $delimiter
     * @return mixed
     */
    public static function cutRightPart($string, $delimiter = '.')
    {
        $part = explode($delimiter, $string);
        return $part[0];
    }


    /**
     * Удаление нижнего подчеркивания
     * @param $string
     * @param string $sing
     * @return array|string
     */
    public static function removeUnderscore($string, $sing = '_')
    {
        $string = preg_split("/[{$sing}]/", $string);
        if (count($string) == 0) {
            return $string;
        }
        $new = '';
        foreach ($string as $item) {
            $new .= ucfirst($item);
        }
        return lcfirst($new);
    }

    /**
     * Перевод в формат snake
     * @param $string
     * @return array|string
     */
    public static function toSnake($string)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
        $return = $matches[0];
        foreach ($return as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $return);
    }

    /**
     * @param string $string
     * @return string
     */
    public static function toCamelCase(string $string)
    {
        return implode('', array_map('ucfirst', preg_split('/[\/-]/', $string)));
    }

    /**
     * Извлекает имя класса из полного названия с пространством имен.
     * @param string $classNs
     * @return string
     */
    public static function extractClassName(string $classNs)
    {
        return mb_substr($classNs, mb_strrpos($classNs, '\\') + 1);
    }
}
