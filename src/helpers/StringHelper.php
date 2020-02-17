<?php

namespace apiman\helpers;

/**
 * Class StringHelper
 * @package apiman\helpers
 */
class StringHelper extends \yii\helpers\StringHelper
{
    /** @var string Значение пустой строки */
    const EMPTY_STR = '';

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
     * @param string $string
     * @return string
     */
    public static function toCamelCase(string $string)
    {
        return implode('', array_map('ucfirst', preg_split('/[\/-]/', $string)));
    }
}
