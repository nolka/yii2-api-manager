<?php
namespace apiman\conditions;

use apiman\base\Field;

/**
 * Class ClassRule
 * @package apiman\conditions
 */
class ClassRule extends Rule implements InterfaceRule
{
    /**
     * @brief
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
     * @brief
     * @return array
     */
    public function getExpandsName()
    {
        $result = [];
        $expands = $this->expands;

        $roleConfigItems = $this->getMatchRoles();
        foreach ($roleConfigItems as $roleConfig) {
            if (!empty($roleConfig->expands)) {
                $expands = array_merge($expands, $roleConfig->expands);
            }
        }

        if (!empty($expands) && is_array($expands)) {
            /** @var Field $expand */
            foreach ($expands as $expand) {
                if ($expand->isAllowMethod()) {
                    $result[] = $expand;
                }
            }
        }

        return $result;
    }
}
