Yii2 api manager
================

Расширение для построения API с гибкой настройкой фильтрации данных, а так-же, автоматической генерации документации к этим программным интерфейсам.

#

### Использование компонента

Предполагается, что API является отдельным приложением в контексте проекта на фреймворке Yii2, и располагается в отдельном каталоге `api`
Для использования компонента необходимо просто прописать его в секции components приложения `api`:

```php
    'fieldManager' => [
        'class' => \apiman\FieldManager::class,
        'enableRules' => true,
        'rules' => require(__DIR__ . '/fields.php'),
        'cache' => true,
    ],
```

Так же, необходимо создать в каталоге с конфигом файл fields.php, в котором будут прописываться правила фильтрации данных.
Пример содержимого:

```php
<?php

use apiman\conditions\TypeRule;

return [
    [
        // Тип правила - объект. Используется для генерации документации и ответов API для AR моделей,
        // и дочерних классов ModelResponse. 
        'type' => TypeRule::TYPE_OBJECT,
        'className' => [
            \api\versions\v1\models\User::class,
        ],
        'rules' => [
            [
                 // Псевдоним для генератора документации. Используется для генерации различных моделей для различных маршрутов
                'classNameDoc' => 'UserOne', 
                'route' => [
                    // Список маршрутов, для которых будут отдаваться перечисленные ниже поля,
                    // и генерироваться документация.

                    // profile
                    'user/profile/index',
                    'user/profile/update',
                ],
                'fields' => [
                    // Список полей модели User, которые будут отдаваться в ответе на запрос
                    'id', 'first_name', 'last_name', 'email', 'registered_at'
                ],
                'expands' => [
                    // Список связей, которые можно развернуть в ответе на запрос
                    'posts',
                ],
            ],
        ],
    ],
];
```

Подразумевается, что API в ответ на любой запрос отдает DataProviderInterface, который уже сериализуется в json, или xml, в зависимости от заголовков запроса

## Генерация документации и отображение

Проблема, которую решает генератор документации - поддержание ее в актуальном состоянии. Вам не нужно писать кучу swagger аннотаций, вместо этого в моделях данных просто нужно описать легковесную структуру, которая содержит в себе название поля, тип данных, и текстовое описание поля, чтоб человеку удобно было разобраться в документации. Далее, в дело вступает генератор документации.
Примечание: Для методов API документацию swagger необходимо прописывать вручную. 

#### Подключение 
Для отображения swagger документации используется расширение [zircote/swagger-php](https://github.com/zircote/swagger-php) 
Чтобы сгенерировать документацию swagger, нужно подключить модуль apiman\Module в конфиг консольного приложения в секцию `modules`:

```php
///...
'modules' => [
    'apiman' => [
        'class' => \apiman\Module::class,
    ],
],
///...
```

После подключения модуля будут доступны 2 команды - на генерацию документации, и на проверку валидности заполненых данных в моделях, и правилах из fields.php. Кроме того, будет проведена проверка на наличие отсутствующих маршрутов в fields.php, или методов API, которые уже не актуальны, и были удалены.
В настоящий момент документация генерируется в 1 файл с множесвом php классов, на основании которого расширение [zircote/swagger-php](https://github.com/zircote/swagger-php) генерирует json объект для отображении в браузере.
Сгенерированный файл располагается по адресу `@api/runtime/fields/swagger.php`. 

Генерация документации выполяется командой `yii apiman/doc/generate`
Валидация документации выполняется командой `yii apiman/doc/validate`

В процессе валидации документации генерируется так-же, как и в первом случае, но при этом сгенерированные данные не записываются в файл

#### Пример

На основе приведенного выше примере из fields.php опишем класс пользователя, на основании которого генерится документация, а также, пример контроллера.

Модель пользователя:

```php
<?php

namespace api\versions\v1\models;

/**
 * Class User
 * @package api\versions\v1\models
 */
class User extends \common\models\User
{
    /**
     * Список полей модели, отдающихся в API
     * @return array
     */
    public function fields(): array
    {
        return [
            'id',
            'first_name',
            'last_name',
            'email',
            'registered_at',
        ];
    }

    /**
     * Список связей, возможных для запроса в API
     * @return array
     */
    public function extraFields(): array
    {
        return [
            'posts',
        ];
    }

    /**
     * Структура данных, описывающая поля модели, на основе которых будет производиться генерация
     * @return array
     */
    public function attributeDetails(): array
    {
        return [
            'id:integer:ID',
            'fname:string:Имя',
            'mname:string:Отчество',
            'lname:string:Фамилия',
            'email:string:Почта',
            'image:string:Ссылка на аватарку',
        ];
    }

    /**
     * Структура данных, описывающая связи модели, которые можно запросить в API 
     * @return array
     */
    public function expandDetails(): array
    {
        return [
            'posts:Список постов пользователя',
        ];
    }

    /**
    * Посты пользователя
    * @return \yii\db\ActiveQuery
     */
    public function getPosts()
    {
        return $this->hasMany(Post::class, ['user_id' => 'id']);
    }
}
```

Поскольку все методы API располагаются в отдельных модулях приложения, опишем контроллер для ранее приведенной модели

```php
<?php

namespace api\versions\v1\modules\user\controllers;

use apiman\base\RestController;
use api\versions\v1\models\User;
use Yii;

/**
 * Class UserController
 * @package api\versions\v1\modules\user\controllers
 */
class UserController extends RestController
{
    /**
     * Данные о пользователе
     * @return User|array
     *
     * @SWG\Get(path="/user/profile",
     *     tags={"User"},
     *     summary="Данные о пользователе",
     *     description="",
     *     produces={"application/json", "application/xml"},
     *     @SWG\Parameter(
     *        in="header",
     *        name="Authorization",
     *        description="Bearer your_token",
     *        default="Bearer ",
     *        type="string"
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="fields",
     *         type="string",
     *         default="id,first_name,last_name,email,posts.id,posts.title",
     *         description="Список полей",
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="expand",
     *         type="string",
     *         default="posts",
     *         description="Список связей",
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Success",
     *         @SWG\Schema(
     *             title="response",
     *             type="object",
     *             @SWG\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/definitions/User_UserUserProfile"
     *             )
     *         )
     *     )
     * )
     *
     */
    public function actionProfile()
    {
        if (!Yii::$app->user->isGuest) {
            return User::findOne(Yii::$app->user->id);
        }
        throw new yii\web\ForbiddenHttpException(); 
    }
}

```

В приведенном выше примере особое внимимание уделим описанию схемы ответа, в которой присутствует строка `ref="#/definitions/User_UserUserProfile"`, А именно часть `User_UserUserProfile`
Именно это название модели, которое будет сгенерировано при вызове генератора документации. 
Название модели генерируется по следующей маске: `%ModelName%_%ModuleName%%ControllerName%%ActionName%`, т.е., поскольку модель пользователя отдается из модуля API `user`, Контроллера `UserController`, экшна `actionProfile`,
название модели будет **`User_UserUserProfile`**

#### Запрос к API

По умолчанию FieldManager на любой запрос к API не отдает никаких данных. Чтобы он отдал какие-либо данные, их нужно запросить через параметры api fields, и expand. Этот механизм работает схоже со стандартными средствами Yii, за исключением того, что запрашиваемые данные нужно перечислять явно. Это было сделано для экономии трафика, ведь возможно, пользователю не нужно запрашивать весь набор данных по профилю, а будет достаточно только имени, и фамилии, тогда ему нужно будет перечислить эти поля следующим образом: %host%/v1/user/profile?field=first_name,last_name