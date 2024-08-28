<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private const SKIP = '__SKIP__';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $result = '';
        $length = strlen($query);
        $paramIndex = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];

            if ($char === '?') {
                $nextChar = $query[$i + 1] ?? null;
                $value = $args[$paramIndex++] ?? null;

                switch ($nextChar) {
                    case 'd':
                        $result .= $this->formatInt($value);
                        $i++;
                        break;
                    case 'f':
                        $result .= $this->formatFloat($value);
                        $i++;
                        break;
                    case 'a':
                        $result .= $this->formatArray($value);
                        $i++;
                        break;
                    case '#':
                        $result .= $this->formatIdentifier($value);
                        $i++;
                        break;
                    default:
                        $result .= $this->formatValue($value);
                        break;
                }
            } elseif ($char === '{') {
                $endBlock = strpos($query, '}', $i);
                if ($endBlock === false) {
                    throw new Exception("Unmatched '{' in template.");
                }
                $blockContent = substr($query, $i + 1, $endBlock - $i - 1);
                $blockParams = array_slice($args, $paramIndex, substr_count($blockContent, '?'));

                if (!in_array(self::SKIP, $blockParams, true)) {
                    $result .= $this->buildQuery($blockContent, $blockParams);
                }

                $paramIndex += count($blockParams);
                $i = $endBlock;
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    public function skip()
    {
        return self::SKIP;
    }

    private function formatInt($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            $value = (int)$value;
        }

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return $value;
        }

        throw new Exception("Value must be an integer or a string of digits.");
    }


    private function formatFloat($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (!is_float($value) && !is_numeric($value)) {
            throw new Exception("Value must be a float.");
        }
        return (string)(float)$value;
    }

    private function formatArray($value): string
    {
        if (!is_array($value)) {
            throw new Exception("Value must be an array.");
        }
        $formatted = [];
        foreach ($value as $key => $val) {
            if (is_string($key)) {
                $formatted[] = $this->escapeIdentifier($key) . ' = ' . $this->formatValue($val);
            } else {
                $formatted[] = $this->formatValue($val);
            }
        }
        return implode(', ', $formatted);
    }

    private function formatIdentifier($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'escapeIdentifier'], $value));
        }
        return $this->escapeIdentifier($value);
    }

    private function formatValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_int($value) || is_float($value)) {
            return (string)$value;
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_string($value)) {
            return "'" . $this->escapeString($value) . "'";
        } else {
            throw new Exception("Unsupported value type.");
        }
    }

    private function escapeString(string $value): string
    {
        return $this->mysqli->real_escape_string($value);
    }

    private function escapeIdentifier($value): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            throw new Exception("Invalid identifier: $value");
        }
        return "`$value`";
    }
}
