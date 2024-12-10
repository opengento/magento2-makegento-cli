<?php

namespace Opengento\MakegentoCli\Exception;

class ExistingClassException extends \Exception
{

    public function __construct(
        string $message = "",
        private readonly string $filePath = '',
        private readonly string $className = '',
        int $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the value of className
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
