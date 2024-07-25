<?php

namespace FpDbTest;

use Exception;
use mysqli;

/*
class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        throw new Exception();
    }

    public function skip()
    {
        throw new Exception();
    }
}
*/
class Database implements DatabaseInterface
{
    public function buildQuery(string $template, array $params): string
    {
        // Обрабатываем условные блоки
        $template = $this->processConditionBlocks($template, $params);

        // Заменяем плейсхолдеры
        $result = preg_replace_callback('/\?(d|f|a|#|)/', function ($matches) use (&$params) {
            $type = $matches[1];
            if (empty($params)) {
                throw new Exception('Недостаточно параметров.');
            }
            $value = array_shift($params);
            return $this->formatValue($value, $type);
        }, $template);

        // Проверяем наличие лишних параметров
        if (!empty($params)) {
            throw new Exception('Слишком много параметров.');
        }

        return $result;
    }

    private function formatValue($value, $type)
    {
        if (is_null($value)) {
            return 'NULL';
        }

        switch ($type) {
            case 'd':
                return intval($value);
            case 'f':
                return floatval($value);
            case 'a':
                if (!is_array($value)) {
                    throw new Exception('Параметр не является массивом.');
                }
                return $this->formatArray($value);
            case '#':
                return is_array($value) ? $this->formatArray($value, true) : $this->escapeIdentifier($value);
            default:
                return $this->escapeValue($value);
        }
    }

    private function formatArray(array $array, $isIdentifier = false)
    {
        $result = [];
        foreach ($array as $key => $value) {
            if ($isIdentifier) {
                $result[] = $this->escapeIdentifier($value);
            } else {
                $result[] = is_string($key)
                    ? $this->escapeIdentifier($key) . ' = ' . $this->escapeValue($value)
                    : $this->escapeValue($value);
            }
        }
        return implode(', ', $result);
    }

    private function escapeValue($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        } elseif (is_bool($value)) {
            return $value ? 1 : 0;
        } elseif (is_string($value)) {
            return "'" . addslashes($value) . "'";
        } else {
            throw new Exception('Неподдерживаемый тип параметра.');
        }
    }

    private function escapeIdentifier($value)
    {
        if (!is_string($value)) {
            throw new Exception('Идентификатор должен быть строкой.');
        }
        return '`' . str_replace('`', '``', $value) . '`';
    }

    private function processConditionBlocks(string $template, array $params): string
    {
        return preg_replace_callback('/{([^}]*)}/', function ($matches) use ($params) {
            foreach ($params as $param) {
                if ($param === $this->skip()) {
                    return '';
                }
            }
            return $matches[1];
        }, $template);
    }

    public function skip()
    {
        return new class {
            public function __toString()
            {
                return 'SKIP';
            }
        };
    }
}

