<?php

namespace RedisDb\Mapper;

use DateTime;
use DateTimeZone;
use RedisDb\Interfaces\IMapper;
use ReflectionClass;

class StdObjectMapper implements IMapper
{
    // TODO: bulk mapper, cache type
    function map(string $type, object $stdObject, $instance = null)
    {
        if ($type === 'DateTime') {
            return new DateTime($stdObject->date, new DateTimeZone($stdObject->timezone));
        }
        $instance = $instance ?? new $type;
        foreach ($stdObject as $key => $value) {
            if (property_exists($instance, $key)) {
                if (is_object($value)) {
                    $reflection = new ReflectionClass($type);
                    $property = $reflection->getProperty($key);
                    $propertyType = $property->getType();
                    if ($propertyType != null) {
                        $typeName = $propertyType->getName();
                        $instance->$key = $this->map($typeName, $value, $instance->$key);
                    } else {
                        $instance->$key = $value;
                    }
                } else {
                    $instance->$key = $value;
                }
            }
        }
        return $instance;
    }
}
