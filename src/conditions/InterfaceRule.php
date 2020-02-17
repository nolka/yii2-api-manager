<?php
namespace apiman\conditions;

/**
 * Interface InterfaceRule
 * @package apiman\conditions
 */
interface InterfaceRule
{
    /**
     * @return array
     */
    public function getFieldsName();

    /**
     * @return array
     */
    public function getExpandsName();
}
