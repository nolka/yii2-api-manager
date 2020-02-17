<?php

namespace apiman\helpers;

use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

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
}
