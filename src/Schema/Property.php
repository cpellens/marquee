<?php

namespace Marquee\Schema;

use Marquee\Data\Entity;
use Marquee\Exception\Exception;

class Property
{
    const FLAG_AUTO_INCREMENT              = 5;
    const FLAG_CURRENT_TIMESTAMP           = 1;
    const FLAG_CURRENT_TIMESTAMP_ON_UPDATE = 2;
    const FLAG_INDEX                       = 3;
    const FLAG_NOT_NULL                    = 6;
    const FLAG_PRIMARY                     = 4;
    const FLAG_UNIQUE                      = 7;
    const TYPE_DATE                        = 4;
    const TYPE_DATETIME                    = 5;
    const TYPE_INTEGER                     = 1;
    const TYPE_STRING                      = 2;
    const TYPE_TEXT                        = 3;
    protected int   $flags;
    protected mixed $defaultValue;
    protected bool  $hidden;

    public function __construct(protected string $name, protected int $type, protected ?int $length = null)
    {
        $this->flags        = 0;
        $this->defaultValue = null;
        $this->hidden       = false;
    }

    public function getLength(): ?int
    {
        if ($this->length) {
            return $this->length;
        }

        return match ($this->type) {
            self::TYPE_INTEGER => 11,
            self::TYPE_STRING => 100,
            default => null
        };
    }

    public function getSqlType(): string
    {
        if ($length = $this->getLength()) {
            return sprintf('%s(%d)', $this->getTypeString(), $this->getLength());
        }

        return $this->getTypeString();
    }

    public static function string(string $name, int $length = 255): static
    {
        return new static($name, self::TYPE_STRING, $length);
    }

    public static function text(string $name): static
    {
        return new static($name, self::TYPE_TEXT);
    }

    public static function integer(string $name): static
    {
        return new static($name, self::TYPE_INTEGER);
    }

    public static function primary(string $name): static
    {
        return static::integer($name)->pKey()->unsigned();
    }

    public static function foreignKey(string $class): static
    {
        if (!is_subclass_of($class, Entity::class)) {
            throw new Exception('Invalid class [%s]', $class);
        }

        $fKey = $class::GetPrimaryKeyName();

        return static::integer($fKey)->unsigned()->index();
    }

    public static function datetime(string $name): static
    {
        return new static($name, self::TYPE_DATETIME);
    }

    public static function date(string $name): static
    {
        return new static($name, self::TYPE_DATE);
    }

    public function applyFlag(int $flag): static
    {
        $this->flags |= 1 << ($flag - 1);

        return $this;
    }

    public function defaultCurrent(): static
    {
        return $this->applyFlag(self::FLAG_CURRENT_TIMESTAMP);
    }

    public function default(mixed $default): static
    {
        $this->defaultValue = $default;

        return $this;
    }

    public function setCurrentOnUpdate(): static
    {
        return $this->applyFlag(self::FLAG_CURRENT_TIMESTAMP_ON_UPDATE);
    }

    public function index(): static
    {
        $this->applyFlag(self::FLAG_INDEX);

        return $this;
    }

    public function getCreateSql(): string
    {
        $modifiers = [];

        if ($dvs = $this->getDefaultValueSql()) {
            $modifiers[] = $dvs;
        }

        if ($this->hasFlag(self::FLAG_AUTO_INCREMENT)) {
            $modifiers[] = 'auto_increment';
        }

        if ($this->hasFlag(self::FLAG_NOT_NULL)) {
            $modifiers[] = 'not null';
        }

        if ($this->hasFlag(self::FLAG_UNIQUE)) {
            $modifiers[] = 'unique';
        }

        if ($this->flags || count($modifiers)) {
            return sprintf('`%s` %s %s', $this->name, $this->getSqlType(), implode(' ', $modifiers));
        }

        return sprintf('`%s` %s', $this->name, $this->getSqlType());
    }

    public function getTypeString(): string
    {
        return match ($this->type) {
            self::TYPE_INTEGER => 'integer',
            self::TYPE_STRING => 'varchar',
            self::TYPE_TEXT => 'text',
            self::TYPE_DATE => 'date',
            self::TYPE_DATETIME => 'datetime',
            default => 'varchar'
        };
    }

    public function isPrimary(): bool
    {
        return $this->hasFlag(self::FLAG_PRIMARY);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function unique(): static
    {
        $this->applyFlag(self::FLAG_UNIQUE);

        return $this;
    }

    public function hide(): static
    {
        $this->hidden = true;

        return $this;
    }

    protected function hasFlag(int $flag): bool
    {
        return ($this->flags & (1 << ($flag - 1))) > 0;
    }

    private function getDefaultValueSql(): ?string
    {
        if ($this->hasFlag(self::FLAG_CURRENT_TIMESTAMP)) {
            return 'default current_timestamp';
        }

        if ($this->hasFlag(self::FLAG_CURRENT_TIMESTAMP_ON_UPDATE)) {
            return 'on update current_timestamp';
        }

        if (isset($this->defaultValue)) {
            return 'default ' . ($this->defaultValue === null ? 'NULL' : $this->getDefaultValueFormat());
        }

        return null;
    }

    private function pKey(): static
    {
        $this->applyFlag(self::FLAG_PRIMARY);
        $this->applyFlag(self::FLAG_AUTO_INCREMENT);
        $this->applyFlag(self::FLAG_NOT_NULL);

        return $this;
    }

    private function unsigned(): static
    {
        return $this;
    }

    private function getDefaultValueFormat(): string
    {
        if (is_string($this->defaultValue)) {
            return sprintf("'%s'", addslashes($this->defaultValue));
        }

        return $this->defaultValue;
    }
}