<?php
namespace apiman\conditions;

use Yii;
use yii\base\InvalidConfigException;
use apiman\base\BaseConfig;
use yii\web\Request;
use apiman\base\Field;

/**
 * Class Rule
 * @package apiman\conditions
 */
class Rule extends BaseConfig
{
    /**
     * @var bool Фильтровать ответ или нет, по умполчанию фильтровать
     */
    public $is_filtered = true;

    /**
     * @var string Название класса для генерации документации
     */
    public $classNameDoc;

    /**
     * @var
     */
    public $route;

    /**
     * @var Role[]
     */
    public $roles = [];

    /**
     * @var array
     */
    public $roleConfig = ['class' => 'apiman\conditions\Role'];

    /**
     * @brief
     * @param $roleDeclarations
     * @return array|bool|mixed
     * @throws \yii\base\InvalidConfigException
     */
    protected function buildRoles($roleDeclarations)
    {
        $builtRoles = [];
        foreach ($roleDeclarations as $key => $role) {
            if (is_array($role)) {
                $role = \Yii::createObject(array_merge($this->roleConfig, $role));
            }
            if (!$role instanceof BaseConfig) {
                throw new InvalidConfigException('Field rule class must implement Rule.');
            }
            $builtRoles[] = $role;
        }
        return $builtRoles;
    }

    /**
     * @brief
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->roles = $this->buildRoles($this->roles);
    }


    /**
     * @brief
     * @param $route
     * @return bool
     */
    public function isCompareRoute($route)
    {
        if (is_array($this->route)) {
            return in_array($route, $this->route);
        } else {
            return $this->route == $route;
        }
    }

    /**
     * @brief
     * @return Role[]
     */
    protected function getMatchRoles()
    {
        $items = empty($this->roles) ? [] : $this->roles;
        if (empty($items)) {
            return [];
        }

        $allowRules = [];

        /**
         * @var string $key
         * @var Role $item
         */
        foreach ($items as $key => $item) {
            if (is_array($item->roles)) {
                foreach ($item->roles as $role) {
                    if ($item->isMatchRole($role) && $item->isAllowMethod()) {
                        $allowRules[] = $items[$key];
                    }
                }
            } elseif (is_string($item->roles)) {
                if ($item->isMatchRole($item->roles) && $item->isAllowMethod()) {
                    $allowRules[] = $items[$key];
                }
            }
        }

        return $allowRules;
    }
}
