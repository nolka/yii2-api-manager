<?php

namespace apiman;

use yii\base\BootstrapInterface;

/**
 * Class Module
 * @package apiman
 */
class Module extends \yii\base\Module implements BootstrapInterface
{
    public function bootstrap($app)
    {
        if ($app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'apiman\controllers';
        }
    }
}