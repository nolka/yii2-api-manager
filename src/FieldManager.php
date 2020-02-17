<?php

namespace apiman;

use apiman\base\Expand;
use apiman\base\Field;
use apiman\conditions\InterfaceRule;
use apiman\conditions\Rule;
use apiman\conditions\TypeRule;
use apiman\helpers\ArrayHelper;
use apiman\helpers\StringHelper;
use Yii;
use yii\base\Arrayable;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\CacheInterface;
use yii\data\DataProviderInterface;

/**
 * FieldManager компонен ограничивает запросы в соответствии с правилами загруженными в
 * параметр rules
 *
 * Пример подключения компонена:
 *
 * 'components' => [
 *     'fieldManager' => [
 *         'class' => \apiman\FieldManager::class,
 *         'enableRules' => true,
 *         'rules' => require(__DIR__ . '/fields.php'),
 *         'cache' => false
 *     ],
 * ],
 *
 * @package apiman
 */
class FieldManager extends Component
{
    /**
     * @var bool настройка активации компонента
     */
    public $enableRules = false;

    /**
     * @var string версия API
     */
    public $version = 'v1';

    /**
     * @var array массив правил в соответствии с которыми формируется набор полей API для запросов fields и expand.
     * Каждый элемент массива определяется как отдельное правило применяемое к указанному
     * в массиве URL.
     *
     * @see fields.php
     */
    public $rules = [];

    /**
     * @var string использовать кеширование для хранения конфигурации с правилами
     */
    public $cache = false;

    /**
     * @var array класс для работы с общими правилами модели
     */
    public $ruleConfig = ['class' => TypeRule::class];

    /**
     * @var string ключ для хранения конфигурации в кеш
     */
    protected $cacheKey = __CLASS__;

    /**
     * @brief
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        if (!$this->enableRules) {
            return;
        }

        if ($this->cache) {
            $this->cache = Yii::$app->get('cache', false);
        }
        if (empty($this->rules)) {
            return;
        }
        $this->rules = $this->buildGroupRules($this->rules);
    }

    /**
     * возвращает класс, который используется в медоте действия
     * @param $dataProvider
     * @return null|string
     */
    private function classInputData($dataProvider)
    {
        if ($dataProvider instanceof Arrayable) {
            return get_class($dataProvider);
        } elseif (is_array($dataProvider) && isset($dataProvider[0]) && $dataProvider[0] instanceof Arrayable) {
            return get_class($dataProvider[0]);
        } elseif ($dataProvider instanceof DataProviderInterface) {
            $models = $dataProvider->getModels();
            if (is_array($models) && !empty($models)) {
                return get_class($models[0]);
            }
        }
        return null;
    }

    /**
     * Функция отдается доступные для поля fields и expand
     * @param $dataProvider
     * @param $fieldsRequested
     * @param $expandRequested
     * @return array
     * @throws InvalidConfigException
     */
    public function filterRequestFields($dataProvider, $fieldsRequested, $expandRequested)
    {
        $className = $this->classInputData($dataProvider);
        list($resolveFields, $rule_enable) = $this->getFields($className, $expandRequested);
        $resolveExpand = $this->getExpandByFields($resolveFields);

        /** @var $rule_enable Rule */
        $fieldsRequested = array_diff($fieldsRequested, ['']);
        if (is_object($rule_enable) && $rule_enable->is_filtered === false && !(is_array($fieldsRequested) && count($fieldsRequested))) { // Если отключен фильтр и не указаны field в get парметрах
            $fieldsRequested = $resolveFields;
        }

        // TODO нужно переделать, если работа с массивом - то поля для разрешения заранее не известны, добавляем их из запроса (Ruslan)
        // TODO Нужно в правилах научитьтся задавать разрешенные поля (Ruslan)
        foreach ($resolveFields as $field1) {
            if (strpos($field1, '*') !== false) {
                foreach ($fieldsRequested as $field2) {
                    $n = trim($field1, '*');
                    if (strpos($field2, $n) === 0) {
                        $resolveFields[] = $n . str_replace($n, '', $field2);
                    }
                }
            }
        }
        return [
            array_intersect($fieldsRequested, $resolveFields),
            array_intersect($expandRequested, $resolveExpand),
            $rule_enable,
        ];
    }

    /**
     * @brief
     * @param $fieldNames
     * @return array
     */
    private function getExpandByFields($fieldNames)
    {
        $result = [];

        /** @var string $field */
        foreach ($fieldNames as $field) {
            $fieldBroken = explode('.', $field);
            if (count($fieldBroken) > 1) {
                $key = null;
                for ($i = 0; $i < count($fieldBroken) - 1; $i++) {
                    $key = $key ? $key . '.' . $fieldBroken[$i] : $fieldBroken[$i];
                    if (!in_array($key, $result)) {
                        $result[] = $key;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Функция выбирает необходимые правила, которые подходят по условиям URL, роли и модели.
     * Затем отбирает только те поля fields и expand которые были запрошены, и отдается результат.
     *
     * Внутри функции реализова рекурсия для поиска полей у запрошенных связей.
     *
     * @param $className
     * @param array $expandRequested
     * @return array|mixed
     * @throws InvalidConfigException
     */
    public function getFields($className, $expandRequested = [])
    {
        list($route) = Yii::$app->request->resolve();

        // получаем все доступные поля в рамках указанных правил
        /** @var Expand[] $expands */
        list($fields, $expands, $rule_enable) = $this->resolveFieldsRoute($className, $route);

        $responseFields = [];

        // проходим по всем зависимостям, которые были запрошены и забираем доступные поля
        /** @var Expand $expand */
        foreach ($expands as $key => $expand) {
            $isFound = false;
            foreach ($expandRequested as $expandName) {
                if ($expand->name == $expandName) {
                    $isFound = true;
                }
            }
            if ($isFound) {
                if ($expand->className) {
                    // обхода дервера зависимостей в связанном классе, необходимо отрезать уже проверенные связи
                    // и отправить на поиски полей для связанного класса
                    list($fieldsChildExpand) = $this->getFields($expand->className, array_map([StringHelper::class, 'cutLeftPart'], $expandRequested));
                    // поля от найденной зависимости должны быть склеены с названием связи через '.'
                    $responseFields = array_merge($responseFields, ArrayHelper::glue($fieldsChildExpand, $expand->name . '.'));
                }
            }
        }

        /** @var Field $field */
        foreach ($fields as $key => $field) {
            $responseFields[] = $field->name;
        }

        return [$responseFields, $rule_enable];
    }

    /**
     * возваращет список правил для переданной модели
     * @param $className
     * @return Rule[]
     */
    protected function getRulesModel($className)
    {
        $ruleModels = [];
        /** @var TypeRule $rule */
        foreach ($this->rules as $rule) {
            if ($rule->type == TypeRule::TYPE_OBJECT && $className) {
                if (is_string($rule->className)) {
                    if ($this->instancesOf($className, $rule->className)) {
                        $ruleModels = array_merge($ruleModels, $rule->rules);
                    }
                } elseif (is_array($rule->className)) {
                    foreach ($rule->className as $model) {
                        if ($this->instancesOf($className, $model)) {
                            $ruleModels = array_merge($ruleModels, $rule->rules);
                        }
                    }
                }
            } elseif ($rule->type == TypeRule::TYPE_ARRAY) {
                $ruleModels = array_merge($ruleModels, $rule->rules);
            }
        }
        return $ruleModels;
    }

    /**
     * TODO: решение не из лучших (websil)
     * сравнение классов
     * @param $className1
     * @param $className2
     * @return bool
     */
    protected function instancesOf($className1, $className2)
    {
        $className2 = new $className2();
        if (new $className1() instanceof $className2) {
            return true;
        }

        return false;
    }

    /**
     * возвращет доступные поля для переданной модели и $URL
     * @param $className
     * @param $route
     * @return array
     */
    public function resolveFieldsRoute($className, $route)
    {
        $fields = $expands = $rule_enable = [];
        $ruleModels = $this->getRulesModel($className);

        /** @var Rule|InterfaceRule $rule */
        foreach ($ruleModels as $rule) {
            if ($rule->isCompareRoute($route) && $rule->isAllowMethod()) {
                $fields = array_merge($fields, $rule->getFieldsName());
                $expands = array_merge($expands, $rule->getExpandsName());
                $rule_enable = $rule;
            }
        }
        return [$fields, $expands, $rule_enable];
    }

    /**
     * @brief
     * @param $ruleDeclarations
     * @return array|bool|mixed
     * @throws \yii\base\InvalidConfigException
     */
    protected function buildGroupRules($ruleDeclarations)
    {
        $builtRules = $this->getBuiltRulesFromCache($ruleDeclarations);
        if ($builtRules !== false) {
            return $builtRules;
        }

        $builtRules = [];
        foreach ($ruleDeclarations as $key => $rule) {
            if (is_array($rule)) {
                $rule = Yii::createObject(array_merge($this->ruleConfig, $rule));
            }
            $builtRules[] = $rule;
        }

        $this->setBuiltRulesCache($ruleDeclarations, $builtRules);

        return $builtRules;
    }

    /**
     * @brief
     * @param $ruleDeclarations
     * @param $builtRules
     * @return bool
     */
    protected function setBuiltRulesCache($ruleDeclarations, $builtRules)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }
        return $this->cache->set([$this->cacheKey, $this->ruleConfig, $ruleDeclarations], $builtRules);
    }

    /**
     * @brief
     * @param $ruleDeclarations
     * @return bool|mixed
     */
    protected function getBuiltRulesFromCache($ruleDeclarations)
    {
        if (!$this->cache instanceof CacheInterface) {
            return false;
        }

        return $this->cache->get([$this->cacheKey, $this->ruleConfig, $ruleDeclarations]);
    }
}
