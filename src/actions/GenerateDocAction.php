<?php

namespace apiman\actions;

use apiman\helpers\RoutesValidator;
use apiman\helpers\SwaggerGenerator;
use apiman\helpers\FileHelper;
use Yii;

class GenerateDocAction extends \yii\base\Action
{
    /**
     * @var bool использовать ли кеш
     */
    public $useCache;
    /**
     * @var bool Проверять ли роуты в экспандах
     */
    public $validateRoutes;

    public function run()
    {
        echo 'Swagger Definition Generator for swagger 2.0 ' . PHP_EOL;
        FileHelper::createDirectory(Yii::getAlias('@api/runtime/fields'));

        $destFile = Yii::getAlias('@api/runtime/fields/swagger.php');
        $fields = include Yii::getAlias('@api/config/fields.php');
        $generator = new SwaggerGenerator($fields, filter_var($this->useCache, FILTER_VALIDATE_BOOLEAN), filter_var($this->validateRoutes, FILTER_VALIDATE_BOOLEAN));
        $generator->generate();
        $code = $generator->getCode();

        if ($generator->hasErrors()) {
            echo $generator->getMessagesAsString() . PHP_EOL;
        }
        file_put_contents($destFile, $code);

        echo 'Routes validator 1.0 ' . PHP_EOL;
        $routeValidator = new RoutesValidator($fields);
        $routeValidator->validate();
        echo $routeValidator->getMessagesAsString() . PHP_EOL;
    }
}
