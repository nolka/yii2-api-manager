<?php

namespace apiman\helpers;

use apiman\exceptions\ExceptionErrorParserFields;
use apiman\response\ModelResponse;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\db\QueryInterface;

/**
 * Выполняет парсинг атрибутов и экспандов для переданного объекта
 * @package apiman\helpers
 */
class AttributeParser
{
    /**
     * @var string
     */
    protected $objectClass;
    /**
     * @var object
     */
    protected $object;

    /** @var array Известные типы данных Swagger */
    protected $knownTypes = [
        'string',
        'number',
        'int',
        'integer',
        'bool',
        'boolean',
        'array',
        'object',
    ];

    /**
     * AttributeParser constructor.
     * @param object $object
     */
    public function __construct($object)
    {
        $this->object = $object;
        $this->objectClass = get_class($object);
    }

    /**
     * Возвращает экземпляр объекта, ассоциированного с парсером
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Возвращает список информацию об атрибутах объекта
     * @return mixed
     */
    public function getAttributeDetails()
    {
        return $this->object->attributeDetails();
    }

    /**
     * Возвращает список экспандов объекта
     * @return mixed
     */
    public function getExpandDetails()
    {
        return $this->object->expandDetails();
    }

    /**
     * Выполняет парсинг атрибутов объекта
     * @return array
     * @throws InvalidConfigException
     */
    public function parseAttributes()
    {
        if (!in_array('attributeDetails', get_class_methods(get_class($this->object)))) {
            throw new InvalidConfigException("Объект {$this->objectClass} не имеет метода attributeDetails");
        }
        return $this->parseAttribute($this->getAttributeDetails());
    }

    /**
     * @param array $attributes Сырой список атрибутов, которые нужно распарсить
     * @return array|null
     * @see AttributeParser::parseAttributes()
     */
    protected function parseAttribute($attributes)
    {
        if (!is_array($attributes)) {
            return null;
        }
        $data = [];
        foreach ($attributes as $key => $attr) {
            if (!is_numeric($key)) {
                $val = $key;
            } else {
                $val = $attr;
            }

            // field_name:data_type:Human readable descriptions
            if (substr_count($val, ':') < 2) {
                throw new ExceptionErrorParserFields("В {$this->objectClass} ошибка в описании атрибута {$val}. Проверьте, что вы указали тип возвращаемых данных и описание параметра");
            }
            list($name, $type, $title) = explode(':', $val, 3);

            if (!$this->isAttributeTypeValid($type)) {
                $knownTypes = implode(', ', $this->knownTypes);
                throw new Exception("Некорректный тип данных: {$type} для {$this->objectClass}::{$name}! Пожалуйста, укажите один из корректных типов данных: {$knownTypes}");
            }
            $data[$name] = [
                'type' => $type,
                'title' => $title,
            ];

            if ($type == 'array') {
                if (isset($attr[0]) && is_array($attr[0])) {
                    $data[$name]['items'][] = $this->parseAttribute($attr[0]);
                } else {
                    $data[$name]['items'] = $this->parseAttribute($attr);
                }
            }
        }
        return $data;
    }

    /**
     * Выполняет парсинг экспандов для переданного объекта
     * @return array
     * @throws InvalidConfigException
     */
    public function parseExpands()
    {
        if (!in_array('expandDetails', get_class_methods(get_class($this->object)))) {
            if ($this->object instanceof ModelResponse) {
                return [];
            }
            throw new InvalidConfigException("Объект {$this->objectClass} не имеет метода expandDetails");
        }
        return $this->parseExpand($this->getExpandDetails());
    }

    /**
     * @param array $attributes Список cырых экспандов
     * @return array|null
     * @throws ExceptionErrorParserFields
     * @throws InvalidConfigException
     * @see AttributeParser::parseExpands()
     */
    protected function parseExpand($attributes)
    {
        if (!is_array($attributes)) {
            return null;
        }
        $data = [];
        foreach ($attributes as $key => $attr) {
            if (!is_numeric($key)) {
                $val = $key;
            } else {
                $val = $attr;
            }

            list($expand, $title) = explode(':', $val);
            $expandData = $this->getObjectExpand($this->object, $expand, ['title' => $title]);
            $data[$expand] = [
                'title' => $title,
                'expandName' => $expand,
                'className' => $expandData['className'],
                'isArray' => $expandData['isArray'],
            ];
        }
        return $data;
    }

    /**
     * Возвращает информацию об экспанде, которая содержит в себе название атрибута экспанда, имя класса, который
     *  "прячется" за указанным атрибутом, является ли этот экспанд массивом, человеко-читаемое название экспанда:
     *  ['expandName' => 'shop', 'className' => 'api\versions\v1\models\Shop', 'isArray' => false, 'title' => 'Магазины']
     * @param object $objectInstance объект для которого парсится экспанд
     * @param string $relationName название экспанда из extraFields
     * @param array $relParams дополнительные параметры: ['title' => 'Магазины']
     * @return array
     * @throws ExceptionErrorParserFields
     * @throws InvalidConfigException
     * @throws \ReflectionException
     */
    public function getObjectExpand($objectInstance, string $relationName, array $relParams)
    {
        $objectClass = get_class($objectInstance);
        $extraFields = $objectInstance->extraFields();
        if (isset($extraFields[$relationName])) {
            $relation = $extraFields[$relationName];
        } elseif (in_array($relationName, $extraFields)) {
            $relation = $relationName;
        } else {
            throw new ExceptionErrorParserFields("У класса {$objectClass} не найден expand: {$relationName}");
        }

        $relTitle = $relParams['title'];
        $resultIsArray = false;

        if (is_string($relation)) {
            $method = 'get' . ucfirst(StringHelper::removeUnderscore($relation));
            $result = PhpDocHelper::getReturnedInDocs($objectClass, $method);
            if ($result !== null) {
                list($expandClassName, $resultIsArray) = $result;
                if (!class_exists($expandClassName)) {
                    throw new InvalidConfigException("Не найден класс {$expandClassName}, указанный в @field для метода {$objectClass}::{$method}");
                }
            } else {
                $link = $objectInstance->$method();
                if ($link === null) {
                    if (($result = PhpDocHelper::getReturnedInDocs($objectClass, $method)) !== null) {
                        list($expandClassName, $resultIsArray) = $result;
                    } else {
                        throw new InvalidArgumentException("Возвращен null для связи {$relationName}. Не хватает данных для построения связи, либо вы забыли указать атрибут @field в phpDoc для метода {$objectClass}::{$method}?");
                    }
                } elseif ($link instanceof QueryInterface) {
                    $expandClassName = $objectInstance->$method()->limit(1)->modelClass;
                    if ($link->multiple) {
                        $resultIsArray = true;
                    }
                } elseif ($link instanceof Model) {
                    $expandClassName = get_class($link);
                } elseif (($result = PhpDocHelper::getReturnedInDocs($objectClass, $method)) !== null) {
                    list($expandClassName, $resultIsArray) = $result;
                    if (!class_exists($expandClassName)) {
                        throw new InvalidConfigException("Не найден класс {$expandClassName}, указанный в @field для метода {$objectClass}::{$method}");
                    }
                } else {
                    $linkType = gettype($link);
                    throw new InvalidArgumentException("Неизвестный возвращаемый тип {$linkType} связи {$relationName}. Забыли указать атрибут @field в phpDoc для метода {$objectClass}::{$method}?");
                }
            }
        } elseif ($relation instanceof \Closure) {
            $link = call_user_func($relation, $objectInstance);

            if ($link instanceof QueryInterface) {
                $expandClassName = $link->limit(1)->modelClass;
                if ($link->multiple) {
                    $resultIsArray = true;
                }
            } elseif ($link instanceof Model) {
                $expandClassName = get_class($link);
            } else {
                $linkType = gettype($link);
                throw new InvalidArgumentException("Неизвестный возвращаемый тип {$linkType} связи {$relationName}. Забыли указать атрибут @field в phpDoc для замыкания в объекте {$objectClass}?");
            }
            $relation = $relationName; // TODO Верно? (Виктор)
        }

        return [
            'expandName' => $relation,
            'className' => $expandClassName,
            'isArray' => $resultIsArray,
            'title' => $relTitle,
        ];
    }

    /**
     * Проверяет что указанное название атрибука указано в fields() модели
     * @param string $attrName
     * @return bool
     */
    public function isAttributeInFields(string $attrName)
    {
        $fields = $this->object->fields(true);
        foreach ($fields as $idx => $callback) {
            $describedAttribute = StringHelper::EMPTY_STR;
            if (is_string($idx) && is_string($callback)) {
                $describedAttribute = $idx;
            } elseif (is_int($idx) && is_string($callback)) {
                $describedAttribute = $callback;
            } elseif (is_string($idx) && is_callable($callback)) {
                $describedAttribute = $idx;
            }

            if (mb_strpos($describedAttribute, ':') !== false) {
                $describedAttribute = StringHelper::byteSubstr($describedAttribute, 0, mb_strpos($describedAttribute, ':'));
            }
            if ($describedAttribute == $attrName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Выполняет валидацию переданного типа данных
     * @param string $type
     * @return bool
     */
    public function isAttributeTypeValid(string $type): bool
    {
        return in_array($type, $this->knownTypes);
    }
}
