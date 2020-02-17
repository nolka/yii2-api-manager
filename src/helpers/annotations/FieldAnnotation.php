<?php
namespace apiman\helpers\annotations;

use apiman\helpers\SwaggerGenerator;
use apiman\helpers\ArrayHelper;

/**
 * @inheritdoc
 */
class FieldAnnotation extends BaseAnnotation
{
    /** @var SwaggerGenerator */
    protected $generator;
    /**
     * описание атрибута
     * @var string
     */
    protected $description;
    /**
     * тип атрибута
     * @var string
     */
    protected $type;
    /**
     * формат атрибута
     * @var string
     */
    protected $format;

    /**
     * @var string
     */
    protected $template = '    /**
     * @var {type}
     * @SWG\Property({format}, description="{description}")
     */
    public ${name};
';

    /**
     * FieldAnnotation constructor.
     * @param SwaggerGenerator $generator
     * @param string $name имя атрибута
     * @param string $description описание атрибута
     * @param string $type тип атрибута
     * @param null $items необязательный аргумент, используемый в случае, если необходимо сгенерировать класс для вложенного массива
     */
    public function __construct(SwaggerGenerator $generator, string $name, $description, string $type, $items = null)
    {
        $this->generator = $generator;

        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->format = $this->getFormat($type, $items);
    }

    /**
     * @inheritdoc
     */
    public function getTemplateData(): array
    {
        return ArrayHelper::wrapKeys(get_object_vars($this));
    }

    /**
     * Возвращает формат поля. Генерирует дополнительный класс для него, если его значением является массив
     * @param $type
     * @param $items
     * @return string
     */
    protected function getFormat(string $type, $items)
    {
        $property = '';
        switch ($type) {
            case 'int':
            case 'integer':
                $property = 'format="int64"';
                break;
            case 'bool':
            case 'boolean':
                $property = 'format="boolean"';
                break;
            case 'smallinteger':
                $property = 'format="int32"';
                break;
            case 'number':
                $property = 'format="number"';
                break;
            case 'string':
                $property = 'format="string"';
                break;
            case 'array':
                $isArray = false;
                if (isset($items[0]) && is_array($items[0])) {
                    $itemsList = $items[0];
                    $isArray = true;
                } else {
                    $itemsList = $items;
                }

                $className = $this->generator->createSimpleClass($this->name, $itemsList);

                if ($isArray) {
                    $property = '@SWG\Items(ref="#/definitions/' . $className . '")';
                } else {
                    $property = 'ref="#/definitions/' . $className . '"';
                    $this->type = 'object'; // в swagger массивы одномерные представлены как объекты
                }
                break;
        }
        return $property;
    }
}
