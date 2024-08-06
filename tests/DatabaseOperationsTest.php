<?php
declare(strict_types=1);

namespace FpDbTest\Tests;

use Exception;
use FpDbTest\DatabaseInterface;

class DatabaseOperationsTest
{
    public function __construct(
        private DatabaseInterface $db,
    ) {}

    /**
     * Проверяет работу операторов сравнения
     * @throws Exception
     */
    public function test(): void
    {
        $cases = [
            // Нет пробелов вокруг знака "="
            [
                'WHERE ?#=?', ['name', 'ivan'],
                'WHERE `name`=\'ivan\'',
            ],
            // По 1 пробелу
            [
                'WHERE ?# = ?d', ['name', 10],
                'WHERE `name` = 10',
            ],
            // По 2 пробела
            [
                'WHERE ?#  =  ?f', ['name', 123.45],
                'WHERE `name`  =  123.45',
            ],
            // Нет пробелов вокруг знака "=" + название столбца прописано
            [
                'WHERE name=?', [321],
                'WHERE name=321',
            ],

            // Нет пробелов вокруг знака "="
            [
                'WHERE ?#=?', ['name', null],
                'WHERE `name` IS NULL',
            ],
            // По 1 пробелу
            [
                'WHERE ?# = ?d', ['name', null],
                'WHERE `name` IS NULL',
            ],
            // По 2 пробела
            [
                'WHERE ?#  =  ?f', ['name', null],
                'WHERE `name`  IS  NULL',
            ],
            // Нет пробелов вокруг знака "=" + название столбца прописано
            [
                'WHERE name=?d', [null],
                'WHERE name IS NULL',
            ],

            // ---------- Отрицание

            // Нет пробелов вокруг знака "="
            [
                'WHERE ?#!=?', ['name', 'ivan'],
                'WHERE `name`!=\'ivan\'',
            ],
            // По 1 пробелу
            [
                'WHERE ?# <> ?d', ['name', 10],
                'WHERE `name` <> 10',
            ],
            // По 2 пробела
            [
                'WHERE ?#  !=  ?f', ['name', 123.45],
                'WHERE `name`  !=  123.45',
            ],
            // Нет пробелов вокруг знака "=" + название столбца прописано
            [
                'WHERE name<>?', [321],
                'WHERE name<>321',
            ],

            // Нет пробелов вокруг знака "!="
            [
                'WHERE ?#!=?', ['name', null],
                'WHERE `name` IS NOT NULL',
            ],
            // По 1 пробелу
            [
                'WHERE ?# <> ?d', ['name', null],
                'WHERE `name` IS NOT NULL',
            ],
            // По 2 пробела
            [
                'WHERE ?#  !=  ?f', ['name', null],
                'WHERE `name`  IS NOT  NULL',
            ],
            // Нет пробелов вокруг знака "=" + название столбца прописано
            [
                'WHERE name<>?d', [null],
                'WHERE name IS NOT NULL',
            ],

            // ---------- Условия

            [
                'WHERE {name=?}', [null],
                'WHERE name IS NULL',
            ],
            [
                'WHERE {name = ?}', [null],
                'WHERE name IS NULL',
            ],
            [
                'WHERE {name  =  ?}', [null],
                'WHERE name  IS  NULL',
            ],
            [
                'WHERE {name<>?}', [null],
                'WHERE name IS NOT NULL',
            ],
            [
                'WHERE {name != ?}', [null],
                'WHERE name IS NOT NULL',
            ],
            [
                'WHERE {name  <>  ?}', [null],
                'WHERE name  IS NOT  NULL',
            ],
            [
                'WHERE {name != ?}', [$this->db->skip()],
                'WHERE ',
            ],

            // ----------

            [
                '? test query', [null],
                'NULL test query',
            ],
            [
                '?test query', [null],
                'NULLtest query',
            ],
            [
                ' ? test query', [null],
                ' NULL test query',
            ],
            [
                '  ? test query', [null],
                '  NULL test query',
            ],
            [
                '=? test query', [null],
                ' IS NULL test query',
            ],
            [
                '= ? test query', [null],
                ' IS NULL test query',
            ],
        ];
        foreach ($cases as [$query, $args, $excepted]) {
            $result = $this->db->buildQuery($query, $args);
            if ($result !== $excepted) {
                $this->throwBuildError($result, $excepted);
            }
        }
    }

    /**
     * Выбрасывает исключение с информацией о некорректном преобразовании
     * @throws Exception
     */
    private function throwBuildError(string $result, string $expected): void
    {
        throw new Exception(
            <<<TEXT
            \nThe result does not meet expectations.
            Expected: $expected
            Result: $result\n
            TEXT
        );
    }
}