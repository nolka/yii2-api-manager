<?php

namespace apiman\helpers;

use apiman\traits\LogMessageTrait;
use Yii;
use yii\web\Application;
use yii\web\Controller;

/**
 * Класс валидации актуальности маршрутов (проверяет, существуют ли в fields.php маршруты, отсутствующие в api)
 *
 * @package apiman\helpers
 */
class RoutesValidator
{
    use LogMessageTrait;

    /**
     * Настройки полей
     * @var mixed
     */
    public $fields;

    /**
     * Версия api
     * @var string
     */
    public $version = 'v1';

    /**
     * RoutesValidator constructor.
     * @param $fields
     */
    public function __construct($fields)
    {
        $this->fields = $fields;
    }

    /**
     * Валидирует маршруты. Выполняет проверки, что все описанные в fields.php маршруты существуют в api, а так же производит поиск
     *  методов API, отсутствующих в fields.php
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function validate()
    {
        $fieldRoutes = $this->getRoutesFromFields();
        $app = $this->getApplication();
        $modules = $app->modules['v1']['modules'];
        $detectedControllersActions = [];
        foreach ($modules as $moduleName => $config) {
            $modules[$moduleName] = new $config['class']($moduleName);
        }
        foreach ($fieldRoutes as $classNameDoc => $routes) {
            foreach ($routes as $route) {
                list($moduleName, $controllerName, $actionName) = explode('/', $route);
                if (!isset($modules[$moduleName])) {
                    $this->error("Не найден модуль {$moduleName}");
                    continue;
                }
                /** @var $controller \yii\web\Controller */
                $controller = $modules[$moduleName]->createController($controllerName)[0];
                if (empty($controller) && empty($controller[0])) {
                    $this->error("{$classNameDoc}: Некорректный маршрут {$route}: Контроллер не найден {$controllerName}");
                    continue;
                }
                $action = $controller->createAction($actionName);
                if (empty($action)) {
                    $this->error("{$classNameDoc}: Некорректный маршрут {$route}: Экшн не найден {$actionName}");
                    continue;
                }
                $detectedControllersActions = array_merge($detectedControllersActions ?? [], array_map(function ($item) use ($moduleName, $controllerName) {
                    return "{$moduleName}/{$controllerName}/{$item}";
                }, $this->getActionsList($controller)));
            }
        }

        $fieldRoutesList = [];
        foreach (array_values($fieldRoutes) as $routes) {
            $fieldRoutesList = array_merge($fieldRoutesList, $routes);
        }

        $missingRules = array_diff(array_unique($detectedControllersActions), array_unique($fieldRoutesList));
        if (!empty($missingRules)) {
            $this->warning("Маршруты не имеют правил в fields.php:\n" . implode("\n", $missingRules));
        }

        return !$this->hasErrors();
    }

    /**
     * Возвращает список экшнов контроллера
     * @param Controller $controller
     * @return array
     */
    protected function getActionsList(Controller $controller)
    {
        $actions = [];
        foreach (get_class_methods($controller) as $methodName) {
            if (StringHelper::startsWith($methodName, 'action') && $methodName != 'actions') {
                $actions[] = mb_strtolower(mb_substr($methodName, 6));
            }
        }
        return $actions;
    }

    /**
     * Ассоциативный массив, ключем которого является classNameDoc из fields.php, значение - массив ассоциированных маршрутов
     * @return array
     */
    public function getRoutesFromFields()
    {
        $routes = [];
        foreach ($this->fields as $fieldConfig) {
            foreach ($fieldConfig['rules'] as $rule) {
                $key = $rule['classNameDoc'] ?? '__TypeArrayRoute';
                $routes[$key] = is_array($rule['route']) ? array_unique($rule['route']) : [$rule['route']];
            }
        }
        return $routes;
    }

    /**
     * Инстанс веб приложения
     * @return Application
     * @throws \yii\base\InvalidConfigException
     */
    public function getApplication()
    {
        $config = ArrayHelper::merge(
            require(Yii::getAlias('@common/config/main.php')),
            require(Yii::getAlias('@api/config/main.php'))
        );

        return (new Application($config));
    }
}
