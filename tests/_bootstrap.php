<?php
defined('YII_APP_BASE_PATH') or define('YII_APP_BASE_PATH', __DIR__ . '/../../');
defined('YII_ENV') or define('YII_ENV', 'test');

require_once(YII_APP_BASE_PATH . '/vendor/autoload.php');
// Environment
require(YII_APP_BASE_PATH . '/common/env.php');

require_once(YII_APP_BASE_PATH . '/vendor/yiisoft/yii2/Yii.php');
require_once(YII_APP_BASE_PATH . '/common/config/bootstrap.php');
require_once(__DIR__ . '/../config/bootstrap.php');
