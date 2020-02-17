<?php
namespace apiman\base;

use api\versions\v1\models\response\ImageThumbsResponse;
use apiman\helpers\StringHelper;
use yii\base\BaseObject;
use yii\base\Model;
use yii\db\QueryInterface;

/**
 * Class Expand описывает поле в 'extraFields'
 * @package apiman
 */
class Expand extends BaseObject
{
    use FieldTrait;

    /**
     * @var string название поля для передачи в API
     */
    public $name;

    /**
     * @var string название класса
     */
    public $className;

    /**
     * @var string название родительского класса
     */
    public $parentClassName;

    /**
     * @var array метода для которых передается текущее поле
     */
    public $method = ['GET', 'POST', 'DELETE', 'PUT'];

    /**
     * @brief
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        $this->definitionClassName();
    }

    /**
     * функция определяем класс который возвращается в поле extraFields
     * @throws \yii\base\InvalidConfigException
     */
    private function definitionClassName()
    {
        $object = \Yii::createObject(['class' => $this->parentClassName]);

        $extraFields = $object->extraFields();

        // возможно использование псевдонима для поля extraFields или функции которая не
        // является геттером для поля extraFields, в коде ниже опраделяем с чем мы имеем дело
        if (isset($extraFields[$this->name]) && is_string($extraFields[$this->name])) {
            $method = 'get' . ucfirst(StringHelper::removeUnderscore($extraFields[$this->name]));
        } else {
            $method = 'get' . ucfirst(StringHelper::removeUnderscore($this->name));
        }

        if ($method == 'getImageThumbs') {
            $this->className = ImageThumbsResponse::class;
        } elseif ($object->hasMethod($method)) {
            // в первую очередь проверяем есть ли у функции в разделе документации поле @field с названием класса
            list($this->className) = $this->getReturnedInDocs($this->parentClassName, $method);
            if (empty($this->className)) {
                // expand могу быть скалярными значениями
                $methodReturnType = (new \ReflectionMethod($this->parentClassName, $method))->getReturnType();
                if (!(!empty($methodReturnType) && !in_array($methodReturnType, ['array']))) {
                    $link = $object->$method();
                    if ($link instanceof QueryInterface) {
                        $this->className = $link->modelClass;
                    } elseif ($link instanceof Model) {
                        $this->className = get_class($link);
                    }
                }
            }
        } else {
            // Возморжно это анонимная функция, проверяем
            if ($object->extraFields()[$this->name] instanceof \Closure) {
                $link = $object->extraFields()[$this->name]($object);
                if ($link instanceof QueryInterface) {
                    $this->className = $link->modelClass;
                }
            } elseif (!empty($relationName = $object->extraFields()[$this->name])) {
                $method = 'get' . ucfirst(StringHelper::removeUnderscore($relationName));
                $link = $object->$method();
                // expand могу быть скалярными значениями
                if ($link instanceof QueryInterface) {
                    $this->className = $link->modelClass;
                }
            }
        }
    }

    /**
     * функция ищет определение возвращаемого значения в документации к функции
     * @param $className
     * @param $method
     * @return array|null
     */
    private function getReturnedInDocs($className, $method)
    {
        $docs = (new \ReflectionMethod($className, $method))->getDocComment();
        $array = explode("\n", $docs);
        $is_array = false;
        foreach ($array as $item) {
            if (mb_strpos($item, '@field') !== false) { // * @field \api\versions\v1\modules\store\models\response\AttributeResponse[]
                if (mb_strpos($item, '[]') !== false) {
                    $is_array = true;
                }
                $item = str_replace(['@field', '*', ' ', '[]', "\r"], '', $item);
                $item = strtok($item, '|');
                if (mb_strpos($item, 'versions') !== false) {
                    return [$item, $is_array];
                }
            }
        }
        return null;
    }
}
