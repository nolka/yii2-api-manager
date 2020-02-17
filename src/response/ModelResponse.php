<?php
namespace apiman\response;

use Yii;
use yii\base\Arrayable;
use yii\base\ArrayableTrait;
use apiman\interfaces\ResponseModelInterface;

/**
 * Class ModelResponse
 * @package apiman\response
 */
class ModelResponse implements Arrayable, ResponseModelInterface
{
    use ArrayableTrait;

    /**
     * ModelResponse constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (!empty($config)) {
            Yii::configure($this, $config);
        }
    }

    /**
     * Returns the list of attribute names.
     * By default, this method returns all public non-static properties of the class.
     * You may override this method to change the default behavior.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        $class = new \ReflectionClass($this);
        $names = [];
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $names[] = $property->getName();
            }
        }
        return $names;
    }

    /**
     * @return array
     */
    public function fields()
    {
        $fields = $this->attributes();
        return array_combine($fields, $fields);
    }

    /**
     * @return void
     */
    public function attributeDetails()
    {
        throw new \BadMethodCallException('Не описан метод attributeDetails для класса: ' . static::class);
    }

    /**
     * @param $name
     * @return boolean
     */
    public function hasMethod($name)
    {
        if (method_exists($this, $name)) {
            return true;
        }

        return false;
    }
}
