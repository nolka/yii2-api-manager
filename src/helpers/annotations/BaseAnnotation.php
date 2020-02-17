<?php
namespace apiman\helpers\annotations;

/**
 * класс представляет собой класс, или его конкретное поле, которое нужно будет вывести в swagger.php
 *  В классе реализован magic-метод __toString
 * @package apiman\helpers
 */
class BaseAnnotation
{
    /**
     * шаблон для генерации строкового представления
     * @var string
     */
    protected $template = '';

    /**
     * название поля
     * @var string
     */
    protected $name;

    /**
     * метод должен возвращать ассоциативный массив, ключами которого являются параметры из шаблона
     *  значениями - значения, на которые будут заменены параметры в шаблоне.
     *  Генерация строки происходит с помощью метода {@link strtr()}
     * @return array
     * @see strtr()
     */
    public function getTemplateData(): array
    {
        throw new \BadFunctionCallException('Метод должен быть реализован в дочернем классе');
    }

    /**
     * геттер для названия поля
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return strtr($this->template, $this->getTemplateData());
    }
}
