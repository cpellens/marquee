<?php

namespace Marquee\Traits;

use Marquee\Core\String\Util;
use Marquee\Data\Entity;
use Marquee\Data\Query;
use Marquee\Data\Relationship;

trait Serializable
{
    public final function getAttributeArray(): array
    {
        $methods = get_class_methods(static::class);
        $data    = [
            '_class' => static::class,
        ];

        foreach ($methods as $method) {
            if ($method === __FUNCTION__) {
                continue;
            }

            if (preg_match('/^get(.+)/', $method, $matches)) {
                $value = $this->{$matches[ 0 ]}();

                if (($value instanceof Query) && ($this instanceof Entity)) {
                    $value = new Relationship($value, $this);
                }

                $data[ Util::ToSnakeCase($matches[ 1 ]) ] =
                    is_object($value) && method_exists($value, 'getAttributeArray')
                        ? $value->getAttributeArray()
                        : (is_object($value)
                        ? get_class($value)
                        :
                        (is_array($value) ? json_encode($value) : (string) $value)
                    );
            }
        }

        return $data;
    }

    public final function __toString(): string
    {
        return json_encode($this->getAttributeArray());
    }
}