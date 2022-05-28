<?php

namespace RedisDb;

use Exception;
use RedisDb\Interfaces\IMapper;
use RedisDb\Interfaces\IRedisConnector;
use RedisDb\Models\BaseEntity;
use RedisDb\Models\BaseEntityDbMap;

class BaseRepository
{
    public const MAX_REDIS_INT = 100000000000;
    protected string $Type;
    protected ?string $DbMap;
    protected string $BaseName;
    protected \Redis $Redis;
    protected ?string $User = null;

    /**
     * 
     * @return int microseconds
     */
    static function GetTime(): int
    {
        $timeOfDay = gettimeofday();
        return $timeOfDay['sec'] * 1000000 + $timeOfDay['usec'];
    }

    private function Connect(): void
    {
        $this->Redis = $this->connector->get();
    }

    function GetConnection(): \Redis
    {
        if (!isset($this->Redis)) {
            $this->Connect();
        }
        return $this->Redis;
    }

    public function __construct(private IRedisConnector $connector, private IMapper $mapper, string $type, ?string $dbMap = null)
    {
        $this->Type = $type;
        $this->DbMap = $dbMap;

        $this->BaseName = str_replace(
            'Entity',
            '',
            strpos($type, '\\') !== false ?
                substr(strrchr($type, "\\"), 1)
                : $type
        );

        if (!isset($this->Redis)) {
            $this->Connect();
        }
    }

    public function GetType()
    {
        return $this->Type;
    }

    function WithUser(?string $user = null)
    {
        $this->User = $user;
    }

    /**
     * 
     * @param int $page 
     * @param int $size 
     * @return {list: BaseEntity[], count: int} 
     */
    public function GetList(
        int $page = 1,
        int $size = 10,
        ?string $ordering = BaseEntityDbMap::ORDER_BY_CreatedOn,
        int $order = 1,
        $rangeFilter = null
    ): array {
        $zKey = "Keys:{$this->BaseName}:$ordering";
        $objects = [];
        $count = 0;

        $start = ($page - 1) * $size;
        $end = $start + $size - 1;
        $ids = [];
        if ($rangeFilter) {
            if ($size > 0) {
                // get data only if requested
                $ids = $order > 0
                    ? $this->Redis->zRangeByScore($zKey, $rangeFilter['start'], $rangeFilter['end'], ['limit' => [$start, $size]])
                    : $this->Redis->zRevRangeByScore($zKey, $rangeFilter['end'], $rangeFilter['start'], ['limit' => [$start, $size]]);
            }
            $count = $this->Redis->zCount($zKey, $rangeFilter['start'], $rangeFilter['end']);
        } else {
            if ($size > 0) {
                // get data only if requested
                $ids = $order > 0 ? $this->Redis->zRange($zKey, $start, $end) : $this->Redis->zRevRange($zKey, $start, $end);
            }
            $count = $this->Redis->zCard($zKey);
        }
        if (count($ids) > 0) {
            $rawData = $this->Redis->mget(array_map(fn ($Id) => "{$this->BaseName}:$Id", $ids));
            $objects = $rawData ? array_map(fn ($json) => $json ? $this->Instantiate($this->Type, json_decode($json, false)) : null, $rawData) : [];
        }
        return ['list' => $objects, 'count' => $count];
    }

    public function GetByIds(array $Ids): array
    {
        $result = [];
        $jsonList = $this->Redis->mget(array_map(fn ($x) => "{$this->BaseName}:$x", $Ids));
        if ($jsonList) {
            foreach ($jsonList as $json) {
                if ($json !== false) {
                    $array = json_decode($json, false);
                    $instance = $this->Instantiate($this->Type, $array);
                    $result[] = $instance;
                }
            }
        }
        return $result;
    }

    public function GetById(string $Id): ?BaseEntity
    {
        $json = $this->Redis->get("{$this->BaseName}:$Id");
        if ($json !== false) {
            $array = json_decode($json, false);
            $instance = $this->Instantiate($this->Type, $array);
            return $instance;
        }
        return null;
    }

    /**
     * 
     * @param array<string,mixed> $keys 
     * @return {list: BaseEntity[], count: int}
     */
    public function GetByKeys(array $keys, int $page = 1, int $size = 10, int $order = 1, string $orderBy = BaseEntityDbMap::ORDER_BY_CreatedOn): array
    {
        $objects = [];
        $zKey = "IX:{$this->BaseName}";
        foreach ($keys as $name => $value) {
            $zKey .= '-' . $name  . ':' . $value;
        }
        $zKey .= ':' . $orderBy;
        if ($size > 0) {
            // get data only if requested
            $start = ($page - 1) * $size;
            $end = $start + $size - 1;
            $ids = $order > 0 ? $this->Redis->zRange($zKey, $start, $end) : $this->Redis->zRevRange($zKey, $start, $end);
            $rawData = $this->Redis->mget(array_map(fn ($Id) => "{$this->BaseName}:$Id", $ids));
            $objects = $rawData ? array_map(fn ($json) => $this->Instantiate($this->Type, json_decode($json, false)), $rawData) : [];
        }
        $count = $this->Redis->zCard($zKey);
        return ['list' => $objects, 'count' => $count];
    }

    public function GetUnique(array $keys): ?BaseEntity
    {
        $key = "UIX:{$this->BaseName}";
        foreach ($keys as $name => $value) {
            $key .= '-' . $name . ':' . $value;
        }
        $Id = $this->Redis->get($key);
        if ($Id) {
            $json = $this->Redis->get("{$this->BaseName}:$Id");
            if ($json !== false) {
                $array = json_decode($json, false);
                $instance = $this->Instantiate($this->Type, $array);
                return $instance;
            }
        }
        return null;
    }

    public function Create(BaseEntity $entity)
    {
        $entity->Id = self::uuidv4();
        $entity->CreatedOn = self::GetTime();
        $entity->CreatedBy = $this->User;
        $status = $this->Update($entity);
        return $status;
    }

    public function Update(BaseEntity $entity)
    {
        $entity->UpdatedOn = self::GetTime();
        $entity->UpdatedBy = $this->User;
        $oldEntity = $this->GetById($entity->Id);
        $result = $this->Redis->set("{$this->BaseName}:{$entity->Id}", json_encode($entity));
        if ($result) {
            $this->Index($entity, $oldEntity);
        }
        return $result;
    }

    public function Delete(BaseEntity $entity)
    {
        // remove indexes
        $this->UnIndex($entity);
        return $this->Redis->del("{$this->BaseName}:{$entity->Id}");
    }

    public function DeleteById(string $Id)
    {
        $entity = $this->GetById($Id);
        return $this->Delete($entity);
    }

    protected function Instantiate(string $type, object $stdObject)
    {
        return $this->mapper->map($type, $stdObject);
    }

    protected function UnIndex(BaseEntity $entity): void
    {
        $this->Redis->zRem("Keys:{$this->BaseName}:" . BaseEntityDbMap::ORDER_BY_CreatedOn, $entity->Id);
        $orderingList = [];
        if ($this->DbMap !== null) {
            /**
             * @var BaseEntityDbMap $map
             */
            $map = $this->DbMap;
            // Remove (Sorting)
            foreach ($map::$Positions as $key => $options) {
                $orderingList[] = $key;
                $this->Redis->zRem("Keys:{$this->BaseName}:$key", $entity->Id);
            }

            foreach ($map::$Indexes as $index => $options) {
                if ($options['Unique']) {
                    // key value
                    $key = "UIX:{$this->BaseName}";
                    foreach ($options['Properties'] as $property) {
                        $key .= '-' . $property . ':' . $entity->$property;
                    }
                    $this->Redis->del($key);
                } else {
                    // sorted set
                    $keys = [
                        "IX:{$this->BaseName}"
                    ];
                    $count = 1;
                    foreach ($options['Properties'] as $property) {
                        if (is_array($entity->$property)) {
                            $itemsCount = count($entity->$property);
                            $countBefore = $count;
                            $count = $count * $itemsCount;
                            for ($i = $countBefore; $i < $count; $i++) {
                                $keys[$i] = $keys[$i % $countBefore];
                            }
                            foreach ($entity->$property as $k => $listItem) {
                                for ($i = 0; $i < $countBefore; $i++) {
                                    $keys[$i + $k * $countBefore] .= '-' . $property . ':' . $listItem;
                                }
                            }
                        } else {
                            for ($i = 0; $i < $count; $i++) {
                                $keys[$i] .= '-' . $property . ':' . $entity->$property;
                            }
                        }
                    }
                    foreach ($keys as $key) {
                        $this->Redis->zRem($key . ':' . BaseEntityDbMap::ORDER_BY_CreatedOn, $entity->Id);
                        foreach ($orderingList as $orderBy) {
                            $this->Redis->zRem($key . ':' . $orderBy, $entity->Id);
                        }
                    }
                }
            }
            // end removing indexes
        }
    }

    public function CreateIndexes()
    {
        if ($this->DbMap) {
            $data = $this->GetList(1, self::MAX_REDIS_INT);
            foreach ($data['list'] as $entity) {
                $this->Index($entity, null);
            }
        }
    }

    protected function Index(BaseEntity $entity, ?BaseEntity $oldEntity): void
    {
        // use lock to guaranty data consistency and integrity 
        // redis inc(1): if > 1 try again in couple of ms, if == 1 - free to go

        $resetOldIndex = $oldEntity !== null;
        if (!isset($entity->CreatedOn)) {
            $entity->CreatedOn = self::GetTime();
        }
        $this->Redis->zAdd("Keys:{$this->BaseName}:" . BaseEntityDbMap::ORDER_BY_CreatedOn, $entity->CreatedOn, $entity->Id);
        $orderingList = [];
        if ($this->DbMap !== null) {
            /**
             * @var BaseEntityDbMap $map
             */
            $map = $this->DbMap;
            // Positions (Sorting)
            foreach ($map::$Positions as $key => $options) {
                $value = 0; // PHP_INT_MAX;
                $scale = 1000;
                $maxStops = 10;
                $stops = 1;
                // var_dump(PHP_INT_MAX);
                // TODO: combine order by multiple fields
                foreach ($options['Properties'] as $property) {
                    if ($entity->$property !== null) {
                        if (is_numeric($entity->$property)) {
                            $value += $entity->$property;
                        } else if (is_string($entity->$property)) {
                            $letters = str_split($entity->$property);
                            foreach ($letters as $char) {
                                if ($stops > $maxStops) {
                                    break;
                                }
                                $value *= $scale;
                                $value += mb_ord($char);
                                $stops++;
                            }
                        } else {
                            throw new Exception("Value can't be represented as a number.");
                        }
                    }
                }
                $this->Redis->zAdd("Keys:{$this->BaseName}:$key", $value, $entity->Id);
                $orderingList[$key] = $value;
            }

            // Indexes
            foreach ($map::$Indexes as $index => $options) {
                if ($options['Unique']) {
                    // key value
                    $key = "UIX:{$this->BaseName}";
                    $oldKey = $key;
                    $hasValue = false;
                    foreach ($options['Properties'] as $property) {
                        if ($entity->$property !== null) {
                            $hasValue = true;
                        }
                        $key .= '-' . $property . ':' . $entity->$property;
                        if ($resetOldIndex) {
                            $oldKey .= '-' . $property . ':' . $oldEntity->$property;
                        }
                    }
                    if ($hasValue) {
                        $existentKeyValue = $this->Redis->get($key);
                        if ($existentKeyValue && $existentKeyValue !== $entity->Id) {
                            throw new Exception("This key is not unique: $key.");
                        }
                        $this->Redis->set($key, $entity->Id);
                    }
                    if ($resetOldIndex && $key !== $oldKey) {
                        $this->Redis->del($oldKey);
                    }
                } else {
                    $keysToRemove = [
                        "IX:{$this->BaseName}"
                    ];

                    // sorted set
                    $keys = [
                        "IX:{$this->BaseName}"
                    ];
                    $count = 1;
                    foreach ($options['Properties'] as $property) {
                        if (is_array($entity->$property)) {
                            $itemsCount = count($entity->$property);
                            $countBefore = $count;
                            $count = $count * $itemsCount;
                            for ($i = $countBefore; $i < $count; $i++) {
                                $keys[$i] = $keys[$i % $countBefore];
                                if ($resetOldIndex) {
                                    $keysToRemove[$i] = $keysToRemove[$i % $countBefore];
                                }
                            }
                            foreach ($entity->$property as $k => $listItem) {
                                for ($i = 0; $i < $countBefore; $i++) {
                                    $keys[$i + $k * $countBefore] .= '-' . $property . ':' . $listItem;
                                }
                            }
                            if ($resetOldIndex) {
                                foreach ($oldEntity->$property as $k => $listItem) {
                                    for ($i = 0; $i < $countBefore; $i++) {
                                        $keysToRemove[$i + $k * $countBefore] .= '-' . $property . ':' . $listItem;
                                    }
                                }
                            }
                        } else {
                            for ($i = 0; $i < $count; $i++) {
                                $keys[$i] .= '-' . $property . ':' . $entity->$property;
                                if ($resetOldIndex) {
                                    $keysToRemove[$i] .= '-' . $property . ':' . $oldEntity->$property;
                                }
                            }
                        }
                    }
                    foreach ($keysToRemove as $key) {
                        $this->Redis->zRem($key . ':' . BaseEntityDbMap::ORDER_BY_CreatedOn, $entity->Id);
                        foreach ($orderingList as $orderBy => $value) {
                            $this->Redis->zRem($key . ':' . $orderBy, $entity->Id);
                        }
                    }
                    foreach ($keys as $key) {
                        $this->Redis->zAdd($key . ':' . BaseEntityDbMap::ORDER_BY_CreatedOn, $entity->CreatedOn, $entity->Id);
                        foreach ($orderingList as $orderBy => $value) {
                            $this->Redis->zAdd($key . ':' . $orderBy, $value, $entity->Id);
                        }
                    }
                }
            }
        }
    }

    static function uuidv4($data = null)
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
