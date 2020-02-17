<?php
namespace apiman\base;

use yii\base\BaseObject;

/**
 * Class Field описывает поле в 'fields'
 * @package apiman
 */
class Field extends BaseObject
{
    use FieldTrait;

    /**
     * @var string название поля для передачи в API
     */
    public $name;

    /**
     * @var array метода для которых передается текущее поле
     */
    public $method = ['GET', 'POST', 'DELETE', 'PUT'];
}
