<?php
namespace apiman\data;

use apiman\helpers\ArrayHelper;
use yii\base\Arrayable;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\data\DataProviderInterface;
use apiman\FieldManager;
use apiman\conditions\Rule;
use apiman\interfaces\ResponseModelInterface;

/**
 * Class Serializer
 * @package app\base\components
 */
class Serializer extends \yii\rest\Serializer
{

    /**
     * Переименован по прозьбе фронта
     * @var string the name of the HTTP header containing the information about the number of data items in each page.
     * This is used when serving a resource collection with pagination.
     */
    public $perPageHeader = 'X-Pagination-Per__Page';

    /**
     * @var
     */
    public $fields = [];

    /**
     * @var
     */
    public $expand = [];

    /**
     * @var ActiveDataProvider|ArrayDataProvider|array
     */
    private $_data;

    /**
     * @var Rule
     */
    public $rules;

    /**
     * @brief
     * @param mixed $data
     * @return array|mixed|null
     */
    public function serialize($data)
    {
        $this->_data = $data;
        if ($this->_data instanceof Model && $this->_data->hasErrors()) {
            return $this->serializeModelErrors($this->_data);
        } elseif ($this->_data instanceof Arrayable || (is_array($this->_data) && isset($this->_data[0]) && $this->_data[0] instanceof Arrayable)) {
            return $this->serializeModel($this->_data);
        } elseif ($this->_data instanceof DataProviderInterface) {
            return $this->serializeDataProvider($this->_data);
        } elseif (is_array($this->_data)) {
            return $this->serializeArray($this->_data);
        }
        return $data;
    }

    /**
     * Наброски, пока не используется
     * @param $_data
     */
    private function serializeResponseModel(ResponseModelInterface $_data)
    {
        list($fields, $expands) = $this->getRequestedFields();

        $return = [];

        if (is_array($expands) && count($expands)) {
            foreach ($expands as $expand) {
                if ($this->_data->$expand instanceof DataProviderInterface) {
                    $return[$expand] = $this->serializeDataProvider($this->_data->$expand);
                }
            }
        }
        return $return;
    }


    /**
     * Обработка массива
     * @param $data
     * @return array|null
     */
    protected function serializeArray($data)
    {
        if ($this->request->getIsHead()) {
            return null;
        }
        if ($this->response->getStatusCode() == 422) { // Ошибка валидации
            return $data;
        }
        list($fields, $expands) = $this->getRequestedFields();
        if (is_array($data) && isset($data[0])) { // Не Ассоциативный массив
            $result = [];
            foreach ($data as $item) {
                $result[] = ArrayHelper::filter(ArrayHelper::toArray($item), $fields);
            }
            return $result;
        } else {
            return ArrayHelper::filter($data, $fields);
        }
    }

    /**
     * @brief
     * @param Arrayable $model
     * @return array|null
     */
    protected function serializeModel($model)
    {
        if ($this->request->getIsHead()) {
            return null;
        }
        list($fields, $expands) = $this->getRequestedFields();
        if (!empty($fields)) {
            if (is_array($model)) {
                $r = [];
                foreach ($model as $key => $model_item) {
                    $r[] = $model_item->toArray($fields, $this->allowExpand($fields, $expands));
                }
                return $r;
            }
            return $model->toArray($fields, $this->allowExpand($fields, $expands));
        }
        return [];
    }

    /**
     * исключаем из вывода expand поля которых не указываются в fields
     * @param $fields
     * @param $expand
     * @return mixed
     */
    protected function allowExpand($fields, $expand)
    {
        foreach ($expand as $key => $expandItem) {
            $isInclude = false;
            foreach ($fields as $field) {
                if (preg_match('/^' . $expandItem . '.+$/', $field, $matches)) {
                    $isInclude = true;
                }
            }
            if (!$isInclude) {
                unset($expand[$key]);
            }
        }
        return $expand;
    }

    /**
     * @brief
     * @param array $models
     * @return array
     */
    protected function serializeModels(array $models)
    {
        list($fields, $expand) = $this->getRequestedFields();
        $dataItems = [];
        if (!empty($fields)) {
            foreach ($models as $i => $model) {
                if ($model instanceof Arrayable) {
                    $dataItems[$i] = $model->toArray($fields, $this->allowExpand($fields, $expand));
                } elseif (is_array($model)) {
                    $dataItems[$i] = ArrayHelper::toArray($model);
                }
            }
        }
        return $dataItems;
    }

    /**
     * @brief
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    protected function getRequestedFields()
    {
        /** @var FieldManager $component */
        $component = \Yii::$app->get('fieldManager');

        $requestFields = array_map('trim', explode(',', $this->request->get($this->fieldsParam)));
        $requestExpand = array_map('trim', explode(',', $this->request->get($this->expandParam)));
        if ($component->enableRules) {
            $result = $component->filterRequestFields($this->_data, $requestFields, $requestExpand);
            $this->fields = $result[0];
            $this->expand = $result[1];
        }
        return [
            preg_split('/\s*,\s*/', implode(',', $this->fields), -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/\s*,\s*/', implode(',', $this->expand), -1, PREG_SPLIT_NO_EMPTY),
        ];
    }
}
