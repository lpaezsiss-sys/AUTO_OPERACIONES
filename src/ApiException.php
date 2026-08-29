<?php

declare(strict_types=1);

namespace Crm;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @var int */
    public $status;

    /** @var array */
    public $extra;

    /**
     * @param string $message
     * @param int $status
     * @param array $extra
     */
    public function __construct($message, $status = 400, array $extra = array())
    {
        parent::__construct($message, (int) $status);
        $this->status = (int) $status;
        $this->extra = $extra;
    }
}
