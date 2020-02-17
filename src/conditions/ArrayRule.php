<?php
namespace apiman\conditions;

use apiman\base\Field;

/**
 * Class ArrayRule
 * @package apiman\conditions
 */
class ArrayRule extends Rule implements InterfaceRule
{
    /**
     * @return array
     */
    public function getFieldsName()
    {
        $result = [];
        $fields = $this->fields;

        $roleConfigItems = $this->getMatchRoles();
        foreach ($roleConfigItems as $roleConfig) {
            if (!empty($roleConfig->fields)) {
                $fields = array_merge($fields, $roleConfig->fields);
            }
        }

        if (!empty($fields) && is_array($fields)) {
            /** @var Field $field */
            foreach ($fields as $field) {
                if ($field->isAllowMethod()) {
                    $result[] = $field;
                }
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getExpandsName()
    {
        return [];
    }
}
