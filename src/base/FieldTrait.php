<?php
namespace apiman\base;

/**
 * Trait FieldTrait
 * @package apiman\base
 */
trait FieldTrait
{
    /**
     * доступен ли правило для текущего метода
     * @return bool
     */
    public function isAllowMethod()
    {
        $method = \Yii::$app->request->getMethod();
        if (is_array($this->method) && in_array($method, $this->method)) {
            return true;
        } elseif (is_string($this->method) && strtoupper($this->method) == $method) {
            return true;
        }
        return false;
    }
}
