<?php
namespace apiman\conditions;

/**
 * Interface InterfaceRule
 * @package apiman\conditions
 */
interface InterfaceRule
{
    /**
     * @brief
     * @return array
     */
    public function getFieldsName();

    /**
     * @brief
     * @return array
     */
    public function getExpandsName();
}
