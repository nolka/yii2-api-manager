<?php
namespace apiman\base;

use yii\filters\auth\HttpBearerAuth;

/**
 * Class RestController
 * @package apiman\base
 */
class RestController extends \yii\rest\Controller
{
    /**
     * @var array
     */
    public $serializer = ['class' => 'apiman\data\Serializer'];

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];
        return $behaviors;
    }
}
