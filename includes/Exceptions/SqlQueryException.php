<?php
namespace App\Exceptions;

use Exception;

class SqlQueryException extends Exception
{
    /** @var string */
    private $query;

    /** @var string */
    private $messageId;

    /** @var string */
    private $error;

    /** @var int */
    private $errorno;

    public function __construct($messageId, $query, $error, $errorno)
    {
        parent::__construct("[$messageId][$error] $query");

        $this->messageId = $messageId;
        $this->query = $query;
        $this->error = $error;
        $this->errorno = $errorno;
    }

    /**
     * @param bool $escape
     *
     * @return string
     */
    public function getQuery($escape = true)
    {
        return $escape ? htmlspecialchars($this->query) : $this->query;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return int
     */
    public function getErrorno()
    {
        return $this->errorno;
    }
}
