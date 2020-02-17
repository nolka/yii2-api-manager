<?php
namespace apiman\conditions;

use yii\di\Instance;
use yii\web\User;
use apiman\base\BaseConfig;

/**
 * Class Role
 * @package apiman\conditions
 */
class Role extends BaseConfig
{
    /**
     * @var array
     */
    public $roles = [];

    /**
     * @var User
     */
    private $_user;

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        $this->_user = Instance::ensure('user', User::class);
    }

    /**
     * @param $role
     * @return bool
     */
    public function isMatchRole($role)
    {
        if ($role === '?') {
            if ($this->_user->getIsGuest()) {
                return true;
            }
        } elseif ($role === '@') {
            if (!$this->_user->getIsGuest()) {
                return true;
            }
        } else {
            if ($this->_user->can($role)) {
                return true;
            }
        }
    }
}
