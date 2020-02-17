<?php
namespace apiman\conditions;

use apiman\FieldManager;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;

/**
 * Class TypeRule
 * @package apiman\conditions
 */
class TypeRule extends BaseObject
{
    /**
     * Типа правила. Класс.
     */
    const TYPE_OBJECT = 'object';

    /**
     * Тип правила. Массив.
     */
    const TYPE_ARRAY = 'array';

    /**
     * @var string тип объекта
     */
    public $type;

    /**
     * @var
     */
    public $className;

    /**
     * @var array
     */
    public $rules = [];

    /**
     * @var array классы
     */
    private $_mappingTypeClass = [
        self::TYPE_OBJECT => ClassRule::class,
        self::TYPE_ARRAY => ArrayRule::class,
    ];

    /**
     * @var FieldManager
     */
    private $_component;

    /**
     * @var string префикс URL, указывается в компоненте в переменной $version
     */
    private $_prefix = 'v1';

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        if (!$this->type || !is_string($this->type) || !isset($this->_mappingTypeClass[$this->type])) {
            throw new InvalidConfigException(\Yii::t('app', 'Тип может содержать только следующие значения: {values}'), [
                'values' => implode(',', array_keys($this->_mappingTypeClass)),
            ]);
        }

        if ($this->type == self::TYPE_OBJECT && empty($this->className)) {
            throw new InvalidConfigException('Не указан класс в одном из правил настройки полей.');
        }

        $this->_prefix = trim($this->_prefix, '/');
        if (empty($this->rules)) {
            return;
        }
        $this->rules = $this->buildRules($this->rules);
    }

    /**
     * @param $route
     * @return array|string
     */
    protected function normalizeRoute($route)
    {
        if (!$this->_prefix) {
            return $route;
        }

        if (is_string($route)) {
            return $this->_prefix . '/' . $route;
        } elseif (is_array($route)) {
            foreach ($route as $key => $routeItem) {
                $route[$key] = $this->_prefix . '/' . $routeItem;
            }
            return $route;
        }

        return $route;
    }

    /**
     * @brief
     * @param $ruleDeclarations
     * @return array|bool|mixed
     * @throws \yii\base\InvalidConfigException
     */
    protected function buildRules($ruleDeclarations)
    {
        $builtRules = [];
        foreach ($ruleDeclarations as $key => $rule) {
            $rule['className'] = $this->className;
            if (is_array($rule)) {
                $rule = \Yii::createObject(array_merge(['class' => $this->_mappingTypeClass[$this->type]], $rule));
            }

            /** @var Rule $rule */
            $rule->route = $this->normalizeRoute($rule->route);

            $builtRules[] = $rule;
        }
        return $builtRules;
    }
}
