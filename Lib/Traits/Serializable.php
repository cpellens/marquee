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

                $key          = Util::ToSnakeCase($matches[ 1 ]);
                $data[ $key ] = $this->_format($value);
            }
        }

        return $data;
    }

    public final function __toString(): string
    {
        return json_encode($this->getAttributeArray());
    }

    protected final function _format($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_object($value)) {
            if ($value instanceof Entity) {
                return $value->getAttributeArray();
            }

            if ($class = get_class($value)) {
                return $class;
            } else {
                return (array) $value;
            }
        }

        if (is_array($value)) {
            return $value;
        }

        return (string) $value;
    }
}