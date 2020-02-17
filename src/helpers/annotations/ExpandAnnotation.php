<?php
namespace apiman\helpers\annotations;

use apiman\helpers\SwaggerGenerator;
use apiman\helpers\ArrayHelper;

/**
 * класс для строкового представления экспандов класса
 * @package apiman\helpers
 */
class ExpandAnnotation extends BaseAnnotation
{
    /**
     * @inheritdoc
     * @var string
     */
    protected $template = '    /**
     * @var {type}
     * @SWG\Property({format}, description="(expand) {description}")
     */
    public ${name};
';

    /**
     * Описание экспанда
     * @var string
     */
    protected $description;

    /**
     * тип экспанда
     * @var string
     */
    protected $format;

    /**
     * ExpandAnnotation constructor.
     * @param SwaggerGenerator $generator
     * @param array $expand
     * @param string $classNameDoc
     */
    public function __construct(SwaggerGenerator $generator, array $expand, string $classNameDoc)
    {
        $this->generator = $generator;

        $this->name = $expand['expandName'];
        $this->description = $expand['title'];
        $this->type = $expand['isArray'] ? 'array' : 'object';

        $this->format = $this->getFormat($classNameDoc);
    }

    /**
     * @inheritdoc
     */
    public function getTemplateData(): array
    {
        return ArrayHelper::wrapKeys(get_object_vars($this));
    }

    /**
     * возвращает формат экспанда
     * @param string $classNameDoc
     * @return string
     */
    protected function getFormat(string $classNameDoc): string
    {
        if ($this->type == 'array') {
            return '@SWG\Items(ref="#/definitions/' . $classNameDoc . '")';
        }
        return 'ref="#/definitions/' . $classNameDoc . '"';
    }
}
