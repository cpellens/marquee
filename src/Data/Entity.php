<?php

namespace Marquee\Data;

use Closure;
use Generator;
use Marquee\Core\Connection\RedisConnection;
use Marquee\Core\DatabaseConnection;
use Marquee\Core\String\Util;
use Marquee\Exception\Exception;
use Marquee\Interfaces\IConnection;
use Marquee\Schema\Property;
use Marquee\Traits\Serializable;
use Symfony\Component\String\Inflector\EnglishInflector;

abstract class Entity
{
    use Serializable;

    protected static string $tableName;
    protected static string $primaryKeyName;
    protected array         $data;
    protected bool          $dirty = false;
    private IConnection     $connection;

    public function __construct(array $data, IConnection $connection)
    {
        $columns = array_keys(static::GetProperties());

        $this->data = array_filter($data, fn($value, $key) => in_array(strtolower($key), $columns),
                                   ARRAY_FILTER_USE_BOTH);
        foreach ($this->data as $i => $k) {
            unset($this->data[ $i ]);
            $this->data[ strtolower($i) ] = $k;
        }

        $this->connection = $connection;
    }

    public function __destruct()
    {
        if ($this->dirty && $this->connection->connected()) {
            $this->save();
        }
    }

    public final function getId(): int
    {
        return $this->data[ static::GetPrimaryKeyName() ] ?? 0;
    }

    public function __get(string $key)
    {
        $properties = static::GetProperties();

        if (in_array(strtolower($key), array_keys($properties)) && isset($this->data[ $key ])) {
            return $this->data[ $key ];
        }

        /**
         * If we have a 'get' method for the property:
         */
        $getMethodString = sprintf('get%s', str_replace('_', '', $key));
        if (method_exists($this, $getMethodString)) {
            $possibleQuery = $this->$getMethodString();

            if ($possibleQuery instanceof Relationship) {
                return $possibleQuery;
            }

            return $possibleQuery;
        }

        return null;
    }

    public function __set(string $name, $value)
    {
        $props      = static::Properties();
        $properties = [];

        foreach ($props as $prop) {
            $properties[ $prop->getName() ] = $prop;
        }

        if (!in_array($name, array_keys($properties))) {
            return;
        }

        $this->data[ $name ] = $value;
        $this->dirty         = true;
    }

    public final static function GetTableName(): string
    {
        if (isset(static::$tableName)) {
            return static::$tableName;
        }

        [ $className ] = array_reverse(explode('\\', static::class));
        [ $toPlural ] = array_reverse(explode(' ', $words = Util::PascalToWords($className)));

        $inflector = new EnglishInflector();
        [ $pluralized ] = array_reverse($inflector->pluralize($toPlural));

        return Util::ToSnakeCase(str_replace($toPlural, $pluralized, $words));
    }

    public final static function GetRepository(IConnection $connection): Repository
    {
        return new Repository($connection, static::class);
    }

    public final static function GetPrimaryKeyName(): string
    {
        if (isset(static::$primaryKeyName)) {
            return static::$primaryKeyName;
        }

        $tableName = static::GetTableName();
        $inflector = new EnglishInflector();

        return Util::ToSnakeCase($inflector->singularize($tableName)[ 0 ] . ' id');
    }

    public function save(): void
    {
        try {
            $this->connection->query(static::class)->update($this->data, $this)->execute();
            $this->invalidate();
        } catch (Exception $e) {
            trigger_error($e->getMessage());
        }
    }

    public function create(string $class, array $params = []): Entity
    {
        $query = $this->connection->query($class)->create(array_merge([
                                                                          static::GetPrimaryKeyName() => $this->getId(),
                                                                      ], $params));

        while ($record = $query->next()) {
            return $record;
        }

        throw new Exception('Could not create instance of [%s]', $class);
    }

    public function attach(self $other): self
    {
        // Goal: Attach $this to $other
        /** @var Entity $class */
        $class = get_class($other);
        $pKey  = $class::GetPrimaryKeyName();

        $this->$pKey = $other->getId();
        $this->connection->query(static::class)->update([ $pKey => $other->getId() ], $this)->execute();

        return $this;
    }

    public function delete(): void
    {
        $this->connection->query(static::class)->where(static::GetPrimaryKeyName(), '=',
                                                       $this->getId())->delete()->execute();

        $relationships = $this->findRelationships();

        foreach ($relationships as $relationship) {
            $relationship->detach($this);
        }
    }

    public final function filter(string $property, Closure $callback): self
    {
        $data = $this->data;

        foreach ($data as $key => $value) {
            if (($key === $property) && $value) {
                $data[ $key ] = $callback($value);
            } else {
                $data[ $key ] = $value;
            }
        }

        return new static($data, $this->connection);
    }

    public function getDateCreated(): string
    {
        return $this->date_created ?? date('Y-m-d H:i:s');
    }

    public function getDateUpdated(): ?string {
        return $this->date_updated;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function serialize(): string
    {
        return serialize($this->data);
    }

    public static final function BuildTable(DatabaseConnection $databaseConnection): Table
    {
        $properties = static::GetProperties();
        $table      = new Table($databaseConnection, static::GetTableName());

        foreach ($properties as $property) {
            $table->addColumnFromProperty($property);
        }

        return $table;
    }

    public static function GetHash(int $id): string
    {
        return md5(sprintf('%s.%d', static::class, $id));
    }

    public static abstract function Properties(): Generator;

    /**
     * @return Relationship[]
     */
    protected function findRelationships(): array
    {
        $methods       = array_filter(get_class_methods(static::class), function (string $which) {
            return (preg_match('/^get.+/', $which) > 0) && $this->$which() instanceof Query;
        });
        $relationships = [];

        foreach ($methods as $method) {
            $relationships[ $method ] = new Relationship($this->$method(), $this);
        }

        return $relationships;
    }

    protected function children(string $class): Relationship
    {
        if (!is_subclass_of($class, Entity::class)) {
            throw new Exception('Class [%s] must be a subclass of [%s]', $class, Entity::class);
        }

        return new Relationship($this->connection->query($class)->where(static::GetPrimaryKeyName(), '=',
                                                                        $this->getId()), $this);
    }

    protected function parent(string $class): Relationship
    {
        if (!is_subclass_of($class, Entity::class)) {
            throw new Exception('Class [%s] must be a subclass of [%s]', $class, Entity::class);
        }

        $pkey = $class::GetPrimaryKeyName();

        return new Relationship($this->connection->query($class)->where($pkey, '=', $this->$pkey), $this);
    }

    private function invalidate(): void
    {
        $hash            = static::GetHash($this->getId());
        $cacheConnection = $this->connection->getCacheConnection();

        if ($cacheConnection instanceof RedisConnection) {
            $cacheConnection->getDriver()->del($hash);
        }
    }

    private static function BaseProperties(): Generator
    {
        yield Property::primary(static::GetPrimaryKeyName());
        yield Property::datetime('date_created')->defaultCurrent();
        yield Property::datetime('date_updated')->default(null)->setCurrentOnUpdate();
    }

    private static function GetProperties(): array
    {
        $props          = static::Properties();
        $baseProperties = static::BaseProperties();
        $propArr        = [];

        foreach ($baseProperties as $baseProperty) {
            $propArr[ $baseProperty->getName() ] = $baseProperty;
        }

        foreach ($props as $prop) {
            $propArr[ $prop->getName() ] = $prop;
        }

        return $propArr;
    }
}