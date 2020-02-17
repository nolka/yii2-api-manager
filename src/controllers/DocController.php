<?php
namespace apiman\controllers;

use apiman\actions\GenerateDocAction;
use apiman\actions\GenerateDocValidateAction;
use apiman\helpers\ArrayHelper;
use yii\console\Controller;

/**
 * Class DocController
 * @package apiman\controllers
 */
class DocController extends Controller
{
    /**
     * @var bool использовать ли кеш
     */
    public $useCache;
    /**
     * @var bool Проверять ли роуты в экспандах
     */
    public $validateRoutes;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        if (in_array($actionID, ['generate', 'validate'])) {
            return ArrayHelper::merge(parent::options($actionID), [
                'useCache', 'validateRoutes'
            ]);
        }
        return parent::options($actionID);
    }

    /**
     * @return array
     */
    public function actions()
    {
        return [
            'generate' => [
                'class' => GenerateDocAction::class, // Валидация Генерации api документации для swagger,
                'useCache' => $this->useCache,
                'validateRoutes' => $this->validateRoutes,
            ],
            'validate' => [
                'class' => GenerateDocValidateAction::class, // Валидация Генерации api документации для swagger
                'useCache' => $this->useCache,
                'validateRoutes' => $this->validateRoutes,
            ]
        ];
    }
}
