<?php

namespace Marquee\Exception;

use Marquee\Traits\Serializable;
use Throwable;

class Exception extends \Exception implements Throwable
{
    use Serializable;

    /**
     * Exception constructor.
     *
     * @param string|Throwable $format
     * @param mixed            ...$params
     */
    public function __construct($format, ...$params)
    {
        parent::__construct(is_string($format)
                                ? (count($params) ? call_user_func_array('sprintf', $params) : $format)
                                : $format->getMessage());
    }

    public function __toString(): string
    {
        return $this->getMessage();
    }
}