<?php
namespace apiman\interfaces;

/**
 * Interface Response
 */
interface ResponseModelInterface
{
    /**
     * Описывает структуру возвращаемых данных в классе, для документации swagger
     * Пример структуры
     *
     * return [
     *     'date:array:Даты' => [
     *         'min:string:Минимальная дата',
     *         'max:string:Максимальная дата',
     *     ],
     *     'statuses:array:Статусы' => [
     *         [
     *             'id:integer:ID статуса',
     *             'name:string:Название статуса',
     *         ],
     *     ],
     *     'shops:array:Магазины' => [
     *         [
     *             'id:integer:ID магазина',
     *             'name:string:Название магазина',
     *         ],
     *     ],
     * ];
     *
     * @return array
     */
    public function attributeDetails();
}
