<?php
namespace apiman\helpers;

use apiman\conditions\TypeRule;
use apiman\exceptions\ExceptionErrorParserFields;
use apiman\helpers\annotations\BaseAnnotation;
use apiman\helpers\annotations\ClassAnnotation;
use apiman\helpers\annotations\ExpandAnnotation;
use apiman\helpers\annotations\FieldAnnotation;
use apiman\response\ModelResponse;
use apiman\helpers\ArrayHelper;
use apiman\helpers\StringHelper;
use apiman\traits\LogMessageTrait;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\UnknownClassException;

/**
 * Генератор документации и тестирование правил написания документации
 * Принцип работы:
 * - сначала генерим сами классы, и их алиасы, привязанные к конкретным маршрутам
 * - вторым этапом генерим экспанды этих классов и связываем их друг с другом
 * @package apiman\helpers
 */
class SwaggerGenerator
{
    use LogMessageTrait;

    /**
     * Использовать кеш по возможности
     * @var bool
     */
    public $useCache = true;

    /**
     * Проверять экспанды на наличие всех маршрутов из родительского класса
     * @var bool
     */
    public $validateRoutes = false;

    /**
     * Список алиасов классов для маршрутов
     * @var array
     */
    protected $classRepository = [];

    /**
     * данные из файла fields
     * @var array
     */
    private $field_data = [];

    /**
     * Отфильтрованные поля
     * @var array
     */
    private $filteredFieldData = [];

    /**
     * Ассоциативный массив сгенерированных классов
     * @var ClassAnnotation[]
     */
    protected $generated = [];

    /**
     * Имя класса, для которого выполняется очередная итерация генерации классов
     * @var string
     */
    protected $currentClassName;

    /**
     * Текущий уникальный ключ маршрута
     * @var string
     */
    protected $currentRepoDocKey;

    /**
     * текущий маршрут для которого выполняется генерация
     * @var string
     */
    protected $currentRoute;

    /**
     * ассоциативный массив парсеров атрибутов. Ключ массива - имя класса, значение - объект парсера
     * @var array
     */
    protected $parsers = [];

    /**
     * SwaggerGenerator constructor.
     * @param array $fields списко правил из конфига fields.php
     */
    public function __construct(array $fields, $useCache = false, $validateRoutes = false)
    {
        $this->field_data = $fields;
        $this->useCache = $useCache;
        $this->validateRoutes = $validateRoutes;
    }

    /**
     * @return string
     */
    public function getGeneratedCacheKey()
    {
        return 'cache_generated_' . get_class($this);
    }

    /**
     * Выполняет генерацию документации swagger. Возвращает true в случае успеха
     * @return bool
     * @throws ExceptionErrorParserFields
     * @throws InvalidConfigException
     * @throws UnknownClassException
     */
    public function generate(): bool
    {
        $this->classRepository = $this->buildRepo($this->field_data);

        if ($this->useCache && ($cache = $this->getCache()) != null) {
            $generated = $cache->get($this->getGeneratedCacheKey());
            if (!empty($generated)) {
                $this->generated = $generated;
                $this->populateExpands();
                return true;
            }
        }

        $this->generateInternal();
        $this->populateExpands();

        return true;
    }

    /**
     * Генерация классов
     * @throws InvalidConfigException
     * @throws UnknownClassException
     */
    protected function generateInternal()
    {
        foreach ($this->classRepository as $routeAlias => $config) {
            $this->createAnnotation($routeAlias, $config);
        }
        if ($this->useCache) {
            $this->getCache()->set($this->getGeneratedCacheKey(), $this->generated, 60);
        }
    }


    /**
     * @return \yii\caching\CacheInterface
     */
    public function getCache()
    {
        return Yii::$app->cache;
    }

    /**
     * Возвращает количество сгенерированных классов
     * @return int
     */
    public function getGeneratedClassesCount(): int
    {
        return count($this->generated);
    }

    /**
     * Возвращает строковое представление всех сгенерированных классов в виде кода на php
     * @return string
     */
    public function getCode(): string
    {
        $code = '<?php' . PHP_EOL;
        foreach ($this->generated as $generatedClass) {
            $code .= $generatedClass . PHP_EOL;
        }
        return $code;
    }

    /**
     * Возвращает массив правил для объектов
     * @param string $type
     * @return mixed
     */
    public function getFieldData($type)
    {
        if (!isset($this->filteredFieldData[$type])) {
            $this->filteredFieldData[$type] = array_filter($this->field_data, function ($item) use ($type) {
                return $item['type'] == $type;
            });

            foreach ($this->filteredFieldData[$type] as $idx => $classDefinition) {
                if (empty($classDefinition['className'])) {
                    $this->error("Не указан className у правила id:{$idx}");
                    continue;
                }
                $classDefinitionNames = implode(', ', $classDefinition['className']);
                foreach ($classDefinition['rules'] as $rule) {
                    if (empty($rule['classNameDoc'])) {
                        $this->error("Не указан classNameDoc для классов: {$classDefinitionNames}");
                    }
                }
            }
        }
        if ($this->hasErrors()) {
            throw new ExceptionErrorParserFields(implode("\n", $this->getErrors()));
        }
        return $this->filteredFieldData[$type];
    }

    /**
     * Возвращает параметры правила для переданного clsassDocName
     * @param null $classDocName
     * @return array
     */
    public function getClassNameDocField($classDocName = null): array
    {
        $items = $this->getFieldData(TypeRule::TYPE_OBJECT);

        if (empty($classDocName)) {
            return $items;
        }
        foreach ($items as $item) {
            foreach ($item['rules'] as $rule) {
                if ($rule['classNameDoc'] == $classDocName) {
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Возвращает правила для конкретного $classNameDoc с учетом маршрута.
     *  Этот метод нужен для ограничения вывода полей
     * @param $classNameDoc
     * @param $route
     * @return array
     */
    public function getRuleData($classNameDoc, $route): array
    {
        $items = $this->getFieldData(TypeRule::TYPE_OBJECT);

        foreach ($items as $item) {
            foreach ($item['rules'] as $rule) {
                if ($rule['classNameDoc'] == $classNameDoc && in_array($route, $rule['route'])) {
                    return $rule;
                }
            }
        }
    }

    /**
     * @param $classNamesList
     * @param $route
     * @return array
     * @throws ExceptionErrorParserFields
     */
    public function getRuleDataByClassName($classNamesList, $route)
    {
        foreach ($this->getFieldData(TypeRule::TYPE_OBJECT) as $item) {
            if (empty(array_intersect($classNamesList, $item['className']))) {
                continue;
            }
            foreach ($item['rules'] as $rule) {
                if (in_array($route, $rule['route'])) {
                    return $rule;
                }
            }
        }
    }

    /**
     * Выполняет создание уникальных имен классов для каждого из маршрутов.
     * @return array
     */
    public function buildRepo($fieldData): array
    {
        $routes = $objects = [];
        $fields = $this->getFieldData(TypeRule::TYPE_OBJECT);

        foreach ($fields as $field) {
            foreach ($field['rules'] as $rule) {
                foreach ($rule['route'] as $route) {
                    $routes[] = $route;
                }
            }
        }

        $routes = array_flip(array_unique($routes));

        foreach ($fields as $field) {
            foreach ($field['rules'] as $rule) {
                foreach ($rule['route'] as $route) {
                    $objects[$rule['classNameDoc'] . $routes[$route]] = [
                        'classNameDoc' => $rule['classNameDoc'],
                        'route' => $route,
                        'fields' => $rule['fields'],
                        'expands' => $rule['expands'] ?? [],
                    ];
                }
            }
        }

        return $objects;
    }

    /**
     * Выполняет поиск classNameDoc из fields.php, которые являются общими для переданного маршрута
     * @param string $route маршрут
     * @return array
     */
    public function getClassNameDocsByRoute(string $route): array
    {
        $list = [];
        foreach ($this->getClassNameDocField() as $fieldData) {
            foreach ($fieldData['rules'] as $rule) {
                if (in_array($route, $rule['route'])) {
                    $list[] = $rule['classNameDoc'];
                }
            }
        }
        return $list;
    }

    /**
     * Возвращает список объекто сгенерированных классов, связанных маршрутом $route
     * @param string $route
     * @return ClassAnnotation[]
     */
    public function getGeneratedClassesByRoute(string $route): array
    {
        $objects = [];
        foreach ($this->generated as $generated) {
            // Классы-хелперы пропускаем, они для экспандов не нужны
            if ($generated->getIsAutoGeneratedClass()) {
                continue;
            }
            // Для большей наглядности ищем именно связанные по маршруту классы, их имена заканчиваются на число
            if ($generated->getRoute() == $route) {
                $objects[] = $generated;
            }
        }
        return $objects;
    }

    /**
     * выполняет проверку существования переданного класса. Выбрасывает исключение, если класс не найден
     * @param string $className
     * @throws UnknownClassException
     */
    public function verifyClassExists(string $className)
    {
        if (!class_exists($className)) {
            throw new UnknownClassException("Не найден класс {$className}");
        }
    }

    /**
     * Возвращает парсер атрибутов для переданного имени класса
     * @param string $className
     * @return AttributeParser
     */
    public function getAttributeParser(string $className): AttributeParser
    {
        if (!array_key_exists($className, $this->parsers)) {
            $this->parsers[$className] = new AttributeParser(new $className());
        }
        return $this->parsers[$className];
    }

    /**
     * Выполняет генерацию списка полей для конкретного класса.
     * @param array $fieldData
     * @return BaseAnnotation[]
     * @throws InvalidConfigException
     * @throws UnknownClassException
     */
    public function generateClassPropertyAnnotations($objectClassName, $ruleData)
    {
        $annotations = [];
        $this->verifyClassExists($objectClassName);

        $parser = $this->getAttributeParser($objectClassName);
        $attributes = $parser->parseAttributes();

        foreach ($attributes as $attr => $params) {
            // Если атрибут не указан в fields правила, игнорируем его
            if (!in_array($attr, $ruleData['fields'])) {
                continue;
            }
            // Если тип атрибута указан как массив, но значение атрибута не описано - это является ошибкой
            if ($params['type'] == 'array' && $params['items'] == null) {
                throw new ExceptionErrorParserFields("Неверно описано поле {$attr} в {$objectClassName}. Указан тип {$params['type']}, но отсутстует описание вложенных элементов");
            }

            if (!($parser->getObject() instanceof ModelResponse) && !$parser->isAttributeInFields($attr) && $attr != '_links' && $attr != '_permissions') {
                throw new ExceptionErrorParserFields("{$objectClassName}: атрибут {$attr} не описан в методе fields()");
            }

            $annotations[] = new FieldAnnotation($this, $attr, $params['title'], $params['type'], $params['items'] ?? null);
        }

        return $annotations;
    }


    /**
     * Добавляет сгенерированный класс к общему списку сгенерированных классов, который используется в дальнейшем
     *  для генерации swagger.php
     * @param string $className
     * @param ClassAnnotation $classAnnotation
     */
    public function addGeneratedClass(string $className, ClassAnnotation $classAnnotation)
    {
        if (!$this->isClassGenerated($className)) {
            $this->generated[$className] = $classAnnotation;
        }
    }

    /**
     * Выполняет проверку, сгенерирован указанный класс, или нет
     * @param string $className
     * @return bool
     */
    public function isClassGenerated(string $className): bool
    {
        return array_key_exists($className, $this->generated);
    }

    /**
     * Выполняет генерацию аннотации класса для полей, чьими результатами являются массивы
     * @param string $name
     * @param array $fields
     * @return string
     */
    public function createSimpleClass(string $name, array $fields)
    {
        $annotations = [];
        $className = 'A_' . $this->currentClassName . '_' . StringHelper::toCamelCase($this->currentRoute) . '_' . StringHelper::mbConvertCase($name) . '_0';

        if ($this->isClassGenerated($className)) {
            return $className;
        }

        if (!empty($this->generated[$className])) {
            return $className;
        }

        foreach ($fields as $name => $params) {
            $annotations[] = new FieldAnnotation($this, $name, $params['title'], $params['type'], $params['items'] ?? null);
        }

        $cls = new ClassAnnotation([], $className, $annotations, $this->currentRoute, []);
        $cls->setIsAutoGeneratedClass(true);
        $this->addGeneratedClass($className, $cls);
        return $className;
    }

    /**
     * Извлекает имя класса из полного пути до него.
     *  Например, вернет MyClass из строки some\namespace\MyClass
     * @param string $namespacedClassName
     * @return string
     */
    protected function extractClassName(string $namespacedClassName)
    {
        return mb_substr($namespacedClassName, mb_strrpos($namespacedClassName, '\\') + 1);
    }

    /**
     * Выполняет генерацию связанных аннотаций для всех классов из fields.php
     *  Т.е., на основе переданного маршрута выбирается список классов для генерации.
     * @param string $routeClassNameDoc Уникальный маршрут, по которому происходит выборка правил для генерации связанных
     * @param array $itemConfig
     * @throws InvalidConfigException
     * @throws UnknownClassException
     */
    public function createAnnotation(string $routeClassNameDoc, array $itemConfig)
    {
        $classNameDoc = $itemConfig['classNameDoc'];
        $fieldData = $this->getClassNameDocField($classNameDoc);
        $realClassName = $fieldData['className'][0];
        $classNameWithoutNs = $this->extractClassName($realClassName);

        $this->currentRoute = $itemConfig['route'];
        $this->currentClassName = $classNameWithoutNs;

        $rules = $this->getRuleData($classNameDoc, $itemConfig['route']);
        $annotations = $this->generateClassPropertyAnnotations($realClassName, $rules);
        $parser = $this->getAttributeParser($realClassName);
        $expands = [];
        foreach ($parser->parseExpands() as $expandAttr => $params) {
            if (in_array($expandAttr, $rules['expands'])) {
                $expands[$expandAttr] = $params;
            }
        }
        $attributes = $parser->parseAttributes();

        $unknownExpands = $this->validateExpands(array_keys($expands), $itemConfig['expands']);
        if (!empty($unknownExpands)) {
            throw new ExceptionErrorParserFields("В {$realClassName}::expandDetails() не описаны экспанды: " . implode(', ', $unknownExpands));
        }
        $unknownFields = array_diff($itemConfig['fields'], array_keys($attributes));
        if (!empty($unknownFields)) {
            throw new ExceptionErrorParserFields("В {$realClassName}::attributeDetails() не описаны поля: " . implode(', ', $unknownFields));
        }
        $bothFields = array_intersect_key($expands, $attributes);
        if (!empty($bothFields)) {
            throw new ExceptionErrorParserFields("Для класса {$realClassName} в fields и expands указаны поля с одинаковым именем: " . implode(', ', array_keys($bothFields)));
        }

        $generatedClassName = $classNameWithoutNs . '_' . StringHelper::toCamelCase($this->currentRoute);
        $classAnnotation = new ClassAnnotation($fieldData['className'], $generatedClassName, $annotations, $this->currentRoute, $expands);
        $this->addGeneratedClass($generatedClassName, $classAnnotation);
    }

    /**
     * Выполняет сравнение экспандов объекта, и в правиле. Если в правилах описаны экспанды отсутствующие в
     *  списке экспандов объекта, вернет "лишние" пункты из правила.
     * @param $objectParams
     * @param $ruleParams
     * @return array|null
     */
    public function validateExpands($objectParams, $ruleParams)
    {
        if (empty($ruleParams)) {
            return null;
        }
        $unknown = array_diff($ruleParams, $objectParams);
        if (!empty($unknown)) {
            return $unknown;
        }
        return null;
    }

    /**
     * Проверяет количество связанных объектов по маршруту с количеством экспандов. Количество связанных объектов не
     *  должно быть меньше количества экспандов - это указывает на ошибки в fields.php.
     * @param ClassAnnotation $classAnnotation
     * @param $linkedObjects
     * @param $expands
     */
    public function verifyLinkedExpandsCount($classAnnotation, $linkedObjects, $expands)
    {
        if (count($linkedObjects) < count($expands)) {
            $missingExpands = array_values(ArrayHelper::getColumn($expands, 'className'));
            $this->error("{$classAnnotation->classNames[0]}: Найдено меньше классов по маршруту '{$classAnnotation->getRoute()}', чем указано в expands!");
            foreach ($linkedObjects as $object) {
                if (($bothExist = array_intersect($object->classNames, $missingExpands)) != []) {
                    foreach ($bothExist as $clsExistName) {
                        array_splice($missingExpands, array_search($clsExistName, $missingExpands), 1);
                    }
                }
            }
            if (!empty($missingExpands)) {
                $this->error(" Нужно добавить маршрут {$classAnnotation->getRoute()} к следующим классам: " . PHP_EOL . ' - ' . implode(PHP_EOL . ' - ', array_unique($missingExpands)));
            }
        }
    }

    /**
     * Проверяет, что у экспанда прописаны все роуты ролительского $classAnnotation
     * @param $classAnnotation
     * @param $expand
     * @throws ExceptionErrorParserFields
     */
    public function validateMissingRoutesForExpand($classAnnotation, $expand)
    {
        $rule = $this->getRuleDataByClassName($classAnnotation->classNames, $classAnnotation->getRoute());
        $expandRule = $this->getRuleDataByClassName([$expand['className']], $classAnnotation->getRoute());
        $classNames = implode(', ', $classAnnotation->classNames);
        if (empty($expandRule)) {
            throw new ExceptionErrorParserFields("Для связи с {$classNames} нужно в экспанд {$expand['className']} добавить маршрут: {$classAnnotation->getRoute()}");
        }
        $missingRoutes = array_diff($rule['route'], $expandRule['route']);
        if (!empty($missingRoutes)) {
            sort($missingRoutes);
            $missingRoutes = implode("',\n '", $missingRoutes);
            throw new ExceptionErrorParserFields("{$classNames} добавьте отсутствующие маршруты в экспанд {$expandRule['classNameDoc']}:\n '{$missingRoutes}',");
        }
    }

    /**
     * Добавляет к сгенерированным аннотациям классов экспанды.
     */
    public function populateExpands()
    {
        foreach ($this->generated as $classNameDoc => $classAnnotation) {
            $linkedObjects = $this->getGeneratedClassesByRoute($classAnnotation->getRoute());
            $expands = $classAnnotation->getExpands();

            foreach ($expands as $expand) {
                if ($this->validateRoutes) {
                    $this->validateMissingRoutesForExpand($classAnnotation, $expand);
                }
                foreach ($linkedObjects as $obj) {
                    if (in_array($expand['className'], $obj->classNames)) {
                        $classAnnotation->addField(new ExpandAnnotation($this, $expand, $obj->getClassNameDoc()));
                    }
                }
            }
        }
    }
}
