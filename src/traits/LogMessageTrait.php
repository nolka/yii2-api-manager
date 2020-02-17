<?php

namespace apiman\traits;

use apiman\helpers\ArrayHelper;

/**
 * Примесь для создания возможности протоколирования хода работы классов
 * @package common\traits
 */
trait LogMessageTrait
{
    public static $lm_message_trace = 0;
    public static $lm_message_error = 1;
    public static $lm_message_warning = 2;

    /**
     * Массив для хранения сообщений, возникающих в ходе работы скрипта
     * @var array
     */
    protected $lmMessageBuffer = [];

    /**
     * Флаг сигнализирующий о наличии сообщений об ошибках
     * @var bool
     */
    protected $lmHasErrors = false;

    /**
     * Добавляет сообщение в лог. Многострочные сообщения будут разбиты по символу перевода каретки на новую строку,
     *  и добавлены построчно
     * @param $level
     * @param $message
     */
    protected function addLogMessage($level, $message)
    {
        if (mb_strpos($message, "\n") !== false) {
            foreach (explode("\n", $message) as $line) {
                $this->addLogMessage($level, $line);
            }
            return;
        }
        $this->lmMessageBuffer[] = [$level, $message];
    }

    /**
     * Добавляет отладочное сообщение
     * @param string $message
     */
    public function trace($message)
    {
        $this->addLogMessage(static::$lm_message_trace, $message);
    }

    /**
     * Добавляет сообщение об ошибке
     * @param string $message
     */
    public function error($message)
    {
        $this->addLogMessage(static::$lm_message_error, $message);
        $this->lmHasErrors = true;
    }

    /**
     * Добавляет сообщение с предупреждением
     * @param string $message
     */
    public function warning($message)
    {
        $this->addLogMessage(static::$lm_message_warning, $message);
    }

    /**
     * Возвращает все сообщения генератора в виде строки
     * @return string
     */
    public function getMessagesAsString()
    {
        $messages = array_map(function ($item) {
            $map = [
                static::$lm_message_trace => 'INFO',
                static::$lm_message_error => 'ERR',
                static::$lm_message_warning => 'WARN',
            ];
            $item[1] = str_pad($map[$item[0]] . ': ', 6) . $item[1];
            return $item;
        }, $this->lmMessageBuffer);
        return implode("\n", ArrayHelper::getColumn($messages, 1));
    }

    /**
     * Возвращает массив сообщений об ошибках
     * @return string[]
     */
    public function getErrors()
    {
        $errorMessages = array_filter($this->lmMessageBuffer, function ($item) {
            return $item[0] == static::$lm_message_error;
        });
        return ArrayHelper::getColumn($errorMessages, 1);
    }

    /**
     * Возвращает true если зафиксированы ошибки, иначе false
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->lmHasErrors;
    }
}
