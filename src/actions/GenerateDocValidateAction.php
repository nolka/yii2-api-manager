<?php

namespace apiman\actions;

use apiman\helpers\RoutesValidator;
use apiman\helpers\SwaggerGenerator;
use Yii;

/**
 * Class GenerateDocValidateAction
 * @package apiman\actions
 */
class GenerateDocValidateAction extends \yii\base\Action
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
        $exitcode = 0;
        echo 'Validating Swagger Definitions' . PHP_EOL;

        $fields = include Yii::getAlias('@api/config/fields.php');
        $generator = new SwaggerGenerator($fields, filter_var($this->useCache, FILTER_VALIDATE_BOOLEAN), filter_var($this->validateRoutes, FILTER_VALIDATE_BOOLEAN));
        try {
            $generator->generate();
            if (!$generator->getGeneratedClassesCount() || $generator->hasErrors()) {
                echo $generator->getMessagesAsString() . PHP_EOL . PHP_EOL;
                $exitcode = 155;
            }
        } catch (\Exception $e) {
            echo "\e[0;41m{$e->getMessage()}\e[0m" . PHP_EOL;
            $exitcode = 255;
        }

        echo 'Routes validator 1.0 ' . PHP_EOL;
        $routeValidator = new RoutesValidator($fields);
        $routeValidator->validate();
        echo $routeValidator->getMessagesAsString() . PHP_EOL;

        if ($routeValidator->hasErrors()) {
            $exitcode = 255;
        }

        exit($exitcode);
    }
}
