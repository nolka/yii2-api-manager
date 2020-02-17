<?php

use apiman\helpers\PhpDocHelper;

/**
 * Class SampleClass
 */
class SampleClass
{
    /**
     * Какое то описание
     * @field models\response\TestResponse
     */
    public function sampleMethod()
    {
        return true;
    }

    /**
     * Какое то описание
     * @field models\response\TestResponse[]
     */
    public function sampleMethodReturnsArray()
    {
        return [];
    }

    /**
     * Возвращает разные результаты
     * @field models\response\TestResponse[]|models\response\ImageThumbsResponse
     */
    public function sampleMethodReturnsMultipleTypes()
    {
        return [];
    }

    /**
     * Какое то описание
     */
    public function sampleMethodWithoutField()
    {
        return [];
    }

    public function sampleMethodWithoutDoc()
    {
        return [];
    }
}

class PhpDocHelperTest extends Codeception\Test\Unit
{
    public function testReturnOneResult()
    {
        $expected = [
            'models\response\TestResponse',
            false,
        ];
        $this->assertEquals($expected, PhpDocHelper::getReturnedInDocs(SampleClass::class, 'sampleMethod'));
    }

    public function testReturnArrayOfItems()
    {
        $expected = [
            'models\response\TestResponse',
            true,
        ];
        $this->assertEquals($expected, PhpDocHelper::getReturnedInDocs(SampleClass::class, 'sampleMethodReturnsArray'));
    }

    /**
     * В настоящий момент у нас в @field не реализована поддержка множественных возвращаемых значений, берем первый доступный
     * @throws ReflectionException
     */
    public function testReturnMultipleTypes()
    {
        $expected = [
            'models\response\TestResponse',
            true,
        ];
        $this->assertEquals($expected, PhpDocHelper::getReturnedInDocs(SampleClass::class, 'sampleMethodReturnsMultipleTypes'));
    }

    public function testMethodWithoutFieldAndDoc()
    {
        $expected = null;
        $this->assertEquals($expected, PhpDocHelper::getReturnedInDocs(SampleClass::class, 'sampleMethodWithoutField'));
        $this->assertEquals($expected, PhpDocHelper::getReturnedInDocs(SampleClass::class, 'sampleMethodWithoutDoc'));
    }
}