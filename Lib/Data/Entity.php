<?php

namespace Marquee\Data;

use Marquee\Core\String\Util;
use Marquee\Exception\Exception;
use Marquee\Interfaces\ICommunicator;
use Marquee\Interfaces\IConnection;
use Marquee\Traits\Serializable;
use Symfony\Component\String\Inflector\EnglishInflector;

abstract class Entity
{
    use Serializable;

    protected static string $tableName;
    protected array         $data;
    protected bool          $dirty = false;
    private IConnection     $connection;

    public function __construct(array $data, IConnection $connection)
    {
        $this->data       = $data;
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

    /**
     * @param string $key
     * @return Relationship|mixed
     */
    public function __get(string $key)
    {
        $properties = static::GetProperties();

        if (in_array($key, array_keys($properties)) && isset($this->data[ $key ])) {
            return $this->data[ $key ];
        }

        /**
         * If we have a get$KEY method
         */
        $getMethodString = sprintf('get%s', $key);
        if (method_exists($this, $getMethodString)) {
            $possibleQuery = $this->$getMethodString();

            if ($possibleQuery instanceof Relationship) {
                return $possibleQuery;
            }

            return $possibleQuery;
        }

        throw new Exception('Unknown Property [%s]', $key);
    }

    public function __set(string $name, $value)
    {
        $properties = static::Properties();

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
        [ $pluralized ] = $inflector->pluralize($toPlural);

        return Util::ToSnakeCase(str_replace($toPlural, $pluralized, $words));
    }

    public final static function GetRepository(ICommunicator $communicator): Repository
    {
        return new Repository($communicator->getConnection(), static::class);
    }

    public final static function GetPrimaryKeyName(): string
    {
        $tableName = static::GetTableName();
        $inflector = new EnglishInflector();

        return Util::ToSnakeCase($inflector->singularize($tableName)[ 0 ] . ' id');
    }

    public function save(): void
    {
        try {
            $this->connection->query(static::class)->update($this->data, $this)->execute();
        } catch (Exception $e) {
            trigger_error($e->getMessage());
        }
    }

    public function create(string $class, array $params = []): Entity
    {
        $query = $this->connection->query($class)->create(array_merge([
                                                                          static::GetPrimaryKeyName() => $this->getId(),
                                                                          'created_at'                => date('Y-m-d H:i:s'),
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

    abstract public static function Properties(): array;

    public function getCreatedAt(): string
    {
        return $this->created_at ?? date('Y-m-d H:i:s');
    }

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

    private static function GetProperties(): array
    {
        $props = static::Properties();

        return array_merge($props, [
            'created_at' => null,
            'updated_at' => null,
        ]);
    }
}