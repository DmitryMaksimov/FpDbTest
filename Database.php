<?php

namespace FpDbTest;

use Exception;
use InvalidArgumentException;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private static $skip = "\f";

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $result = self::build($query, $args);
        if ($result === self::$skip)
            throw new InvalidArgumentException("Used 'skip' value at root!");
        return $result;
    }

    private function build(string $query, array &$args): string
    {
        $regexp = '/(\?([dfa#]?))|(?:\{(.*?)})/';
        $skip = false;
        $callback = function ($matches) use (&$args, &$skip): string
        {
            if ($matches[1])
            {
                $value = array_shift($args);
                $value = $this->format($value, $matches[2]);
                if ($value == self::$skip)
                {
                    $skip = true;
                    return '';
                }
                return $value;
            }
            else
            {
                $result = $this->build($matches[3], $args);
                if ($result === self::$skip)
                    return "";
                return $result;
            }
        };
        $result = preg_replace_callback($regexp, $callback, $query);
        if ($skip)
            return self::$skip;
        return $result;
    }

    private function commonFormat($value): string
    {
        switch(gettype($value))
        {
            case "boolean":
                return (string)(int)$value;
            case "integer":
            case "double":
                return (string)$value;
            case "string":
                return "'".mysqli_escape_string($this->mysqli, $value)."'";
            case "NULL":
                return 'NULL';
            default:
                throw new InvalidArgumentException();
        }
    }

    private function format($value, $format): string
    {
        if ($value === self::$skip)
            return self::$skip;
        switch($format)
        {
            case 'd': return (string)(int)$value;
            case 'f': return (string)(float)$value;
            case 'a':
                return $this->arrayFormat($value, false);
            case '': return $this->commonFormat($value);
            case '#':
                return $this->arrayFormat(is_array($value) ? $value : [$value], true);
            default: throw new Exception("unknown specificator: {$format}");
        }
    }

    private function arrayFormat(array $array, bool $is_id): string
    {
        $is_list = array_is_list($array);
        $result = [];
        foreach ($array as $i => $v)
        {
            if ($v === self::$skip)
                continue;
            if ($is_id)
                $v = '`'.mysqli_escape_string($this->mysqli, $v).'`';
            else
                $v = $this->commonFormat($v);
            if (!$is_list)
            {
                $ni = mysqli_escape_string($this->mysqli, $i);
                $v = '`'.$ni."` = ".$v;
            }
            $result[] = $v;
        }
        return join(", ", $result);
    }

    public function skip()
    {
        return self::$skip;
    }
}
