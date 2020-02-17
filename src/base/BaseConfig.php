<?php

namespace apiman\base;

use Yii;
use yii\base\BaseObject;

/**
 * Class BaseConfig описывет общие правился для групповых правил и для правил
 * для определенного URL.
 *
 * @package apiman\base
 */
class BaseConfig extends BaseObject
{
    use FieldTrait;

    /**
     * @var array доступные поля
     */
    public $fields = [];

    /**
     * @var array доступные зависимости
     */
    public $expands = [];

    /**
     * @var array|string поля $fields и $expand доступнеы только для указанных в переменной методов запроса
     */
    public $method = ['GET', 'POST', 'DELETE', 'PUT'];

    /**
     * @var string класс
     */
    public $className;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->initFields();
        $this->initExpand();
    }

    /**
     * Подготовка объектов для работы с полем
     * @throws \yii\base\InvalidConfigException
     */
    protected function initFields()
    {
        foreach ($this->fields as $i => $field) {
            $config = [
                'class' => $field['class'] ?? Field::class,
            ];
            if (is_string($field)) {
                $config['name'] = $field;
            } elseif (is_array($field)) {
                $config = array_merge($config, $field);
            }

            $field = Yii::createObject($config);
            $this->fields[$i] = $field;
        }
    }

    /**
     * Подготовка объектов для работы с полем
     * @throws \yii\base\InvalidConfigException
     */
    protected function initExpand()
    {
        foreach ($this->expands as $i => $expand) {
            $config = [
                'class' => Expand::class,
            ];
            if (is_string($expand)) {
                $config['name'] = $expand;
            } elseif (is_array($expand)) {
                $config = array_merge($config, $expand);
            }
            $config['parentClassName'] = $this->className[0];
            $this->expands[$i] = Yii::createObject($config);
        }
    }
}
