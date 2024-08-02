<?php
declare(strict_types=1);

namespace FpDbTest\Tests;

use Exception;
use FpDbTest\DatabaseInterface;
use FpDbTest\Exceptions\ResolveArgException;
use stdClass;

class DatabaseTypesTest
{
    public function __construct(
        private DatabaseInterface $db,
    ) {}

    /**
     * Проверяет работу спецификаторов преобразования
     * @throws Exception
     */
    public function test(): void
    {
        $this->testInt();
        $this->testFloat();
        $this->testId();
        $this->testDefault();
        $this->testArray();
    }

    /**
     * Тестирование спецификатора целого числа
     * @throws Exception
     */
    private function testInt(): void
    {
        $cases = [
            [[10], '10'],
            [['10'], '10'],
            [[10.0], '10'],
            [['10.0'], '10'],
            [[10.5], '10'],
            [['10.5'], '10'],
            [[true], '1'],
            [[false], '0'],
            [[null], 'NULL'],
        ];
        foreach ($cases as [$args, $expected]) {
            if (($result = $this->db->buildQuery('?d', $args)) !== $expected) {
                $this->throwConversionError($result, $expected);
            }
        }

        $exceptionCases = [
            ['test'],
            ['10test'],
            ['test10'],
            [[]],
            [[10]],
            [fn ($a) => $a * 2],
            [new stdClass()],
            [(object) ['foo' => 'bar']],
        ];
        foreach ($exceptionCases as $args) {
            $this->testResolveArgException('?d', $args);
        }
    }

    /**
     * Тестирование спецификатора вещественного числа
     * @throws Exception
     */
    private function testFloat(): void
    {
        $cases = [
            [[10], '10'],
            [['10'], '10'],
            [[10.0], '10'],
            [['10.0'], '10'],
            [[10.5], '10.5'],
            [['10.5'], '10.5'],
            [[true], '1'],
            [[false], '0'],
            [[null], 'NULL'],
        ];
        foreach ($cases as [$args, $expected]) {
            if (($result = $this->db->buildQuery('?f', $args)) !== $expected) {
                $this->throwConversionError($result, $expected);
            }
        }

        $exceptionCases = [
            ['test'],
            ['10test'],
            ['test10'],
            [[]],
            [[10]],
            [fn ($a) => $a * 2],
            [new stdClass()],
            [(object) ['foo' => 'bar']],
        ];
        foreach ($exceptionCases as $args) {
            $this->testResolveArgException('?f', $args);
        }
    }

    /**
     * Тестирование спецификатора идентификаторов
     * @throws Exception
     */
    private function testId(): void
    {
        $cases = [
            [['id'], '`id`'],
            [['i`d'], '`i``d`'],
            [[['id', 'name']], '`id`, `name`'],
            [[['i`d', 'na``me']], '`i``d`, `na````me`'],
            [[['foo' => 'bar']], '`bar`'],
            [[0], '`0`'],
            [[1], '`1`'],
            [[10], '`10`'],
            [[10.0], '`10`'],
            [[10.5], '`10.5`'],
            [[true], '`1`'],
            [[false], '``'],
            [[[]], ''],
        ];
        foreach ($cases as [$args, $expected]) {
            if (($result = $this->db->buildQuery('?#', $args)) !== $expected) {
                $this->throwConversionError($result, $expected);
            }
        }

        $exceptionCases = [
            [null],
            [fn ($a) => $a * 2],
            [new stdClass()],
            [(object) ['foo' => 'bar']],
        ];
        foreach ($exceptionCases as $args) {
            $this->testResolveArgException('?#', $args);
        }
    }

    /**
     * Тестирование спецификатора по умолчанию
     * @throws Exception
     */
    private function testDefault(): void
    {
        $cases = [
            [['id'], "'id'"],
            [['i`d'], "'i`d'"],
            [[10], "10"],
            [['10'], "'10'"],
            [[10.0], "10"],
            [['10.0'], "'10.0'"],
            [[10.5], "10.5"],
            [['10.5'], "'10.5'"],
            [[true], "1"],
            [[false], "0"],
            [[null], "NULL"],
        ];
        foreach ($cases as [$args, $expected]) {
            if (($result = $this->db->buildQuery('?', $args)) !== $expected) {
                $this->throwConversionError($result, $expected);
            }
        }

        $exceptionCases = [
            [['id', 'name']],
            [['foo' => 'bar']],
            [[]],
            [fn ($a) => $a * 2],
            [new stdClass()],
            [(object) ['foo' => 'bar']],
        ];
        foreach ($exceptionCases as $args) {
            $this->testResolveArgException('?', $args);
        }
    }

    /**
     * Тестирование спецификатора массива
     * @throws Exception
     */
    private function testArray(): void
    {
        $cases = [
            [[[10, '10']], "10, '10'"],
            [[[10.0, '10.0']], "10, '10.0'"],
            [[[10.5, '10.5']], "10.5, '10.5'"],
            [[[true, false]], "1, 0"],
            [[[null, null]], "NULL, NULL"],
            [[['test', 'string']], "'test', 'string'"],
            [[[]], ""],
            [[['id' => 10, 'name' => 'Test']], "`id` = 10, `name` = 'Test'"],
            [[['id' => 10]], "`id` = 10"],
            [[['10' => 10]], "`10` = 10"],
            [[[10 => 10]], "`10` = 10"],
            [[['is_active' => true]], "`is_active` = 1"],
            [[['deleted_at' => null]], "`deleted_at` = NULL"],
        ];
        foreach ($cases as [$args, $expected]) {
            if (($result = $this->db->buildQuery('?a', $args)) !== $expected) {
                $this->throwConversionError($result, $expected);
            }
        }

        $exceptionCases = [
            ['test'],
            [[[]]],
            [[fn ($a) => $a * 2]],
            [[new stdClass()]],
            [[(object) ['foo' => 'bar']]],
        ];
        foreach ($exceptionCases as $args) {
            $this->testResolveArgException('?a', $args);
        }
    }

    /**
     * Тестирование выбрасывания исключения при некорректных входных данных
     * @noinspection PhpRedundantCatchClauseInspection
     * @throws Exception
     */
    private function testResolveArgException(string $query, array $args): void
    {
        try {
            $query = $this->db->buildQuery($query, $args);
            $success = true;
        } catch (ResolveArgException) {
            $success = false;
        } finally {
            if (!empty($success)) {
                throw new Exception("Missed [ResolveArgException] exception. Query: $query");
            }
        }
    }

    /**
     * Выбрасывает исключение с информацией о некорректном преобразовании
     * @throws Exception
     */
    private function throwConversionError(string $result, string $expected): void
    {
        throw new Exception(
            <<<TEXT
            \nError in converting arguments.
            Expected: $expected
            Result: $result\n
            TEXT
        );
    }
}