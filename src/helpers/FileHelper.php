<?php

namespace apiman\helpers;

/**
 * Работа с файлами
 * @package apiman\helpers
 */
class FileHelper extends \yii\helpers\FileHelper
{
    /**
     * @param string $filename
     * @return mixed
     */
    public static function getExtention(string $filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }
}
