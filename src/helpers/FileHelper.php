<?php

namespace apiman\helpers;

/**
 * Работа с файлами
 * Class FileHelper
 * @package common\helpers
 */
class FileHelper extends \yii\helpers\FileHelper
{
    /**
     * @const 10 Мб в байтах
     */
    const FILE_SIZE_10MB = 10485760;

    /**
     * @const 20 Мб в байтах
     */
    const FILE_SIZE_20MB = 20971520;

    /**
     * @const 10 Кб в байтах
     */
    const FILE_SIZE_10KB = 10240;

    /**
     * @param string $filename
     * @return mixed
     */
    public static function getExtention(string $filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }
}
