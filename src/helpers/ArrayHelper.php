<?php

namespace apiman\helpers;

/**
 * Class ArrayHelper
 * @package apiman\helpers
 */
class ArrayHelper extends \yii\helpers\ArrayHelper
{
    /**
     * К каждому элементу массива прикреивает новую строку
     * @param $array
     * @param $string
     * @param bool $before
     * @return mixed
     */
    public static function glue($array, $string, $before = true)
    {
        foreach ($array as $key => $item) {
            $array[$key] = $before ? $string . $item : $item . $string;
        }

        return $array;
    }

    /**
     * Оборачивает ключи массива в заданные подстроки
     * @param $arr
     * @param string $left
     * @param string $right
     * @return array
     */
    public static function wrapKeys($arr, $left = '{', $right = '}')
    {
        $wrapped = [];
        foreach ($arr as $k => $v) {
            $wrapped["{$left}{$k}{$right}"] = $v;
        }
        return $wrapped;
    }
}
