<?php
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

namespace FpDbTest\Tests;

use Exception;
use FpDbTest\DatabaseInterface;
use FpDbTest\Exceptions\ConditionNotClosedException;

class DatabaseConditionTest
{
    public function __construct(
        private DatabaseInterface $db,
    ) {}

    /**
     * Проверяет корректную работу условных блоков
     * @throws Exception
     */
    public function test(): void
    {
        $cases = [
            [
                'SELECT "test { str" FROM ?#',
                ['users'],
                'SELECT "test { str" FROM `users`',
            ],
            [
                'SELECT "test \{ str" FROM ?#',
                ['users'],
                'SELECT "test \{ str" FROM `users`',
            ],
            [
                'SELECT "test str" FROM ?#{ WHERE name LIKE ?}',
                ['users', $this->db->skip()],
                'SELECT "test str" FROM `users`',
            ],
            [
                'SELECT "test str" FROM ?#{ WHERE name LIKE ?}',
                ['users', 'Ivan'],
                'SELECT "test str" FROM `users` WHERE name LIKE \'Ivan\'',
            ],
            [
                'SELECT "test str" FROM ?#{ WHERE name LIKE ? AND id > ?d AND random = ?}',
                ['users', 'Ivan', 100, true],
                'SELECT "test str" FROM `users` WHERE name LIKE \'Ivan\' AND id > 100 AND random = 1',
            ],
            [
                'SELECT "test str" FROM ?#{ WHERE name LIKE ? AND id > ?d AND random = ?}',
                ['users', $this->db->skip(), 100, true],
                'SELECT "test str" FROM `users`',
            ],
            [
                'SELECT "test str" FROM ?#{ WHERE name LIKE ? AND id > ?d AND random = ?}',
                ['users', 'Ivan', 100, $this->db->skip()],
                'SELECT "test str" FROM `users`',
            ],
        ];
        foreach ($cases as [$query, $args, $excepted]) {
            $result = $this->db->buildQuery($query, $args);
            if ($result !== $excepted) {
                $this->throwBuildError($result, $excepted);
            }
        }

        $missedBracket = [
            [
                'SELECT "test str" FROM ?#{ WHERE name LIKE ?',
                ['users', 'Ivan'],
            ],
            [
                'SELECT "test str" FROM ?#{ WHERE name LIKE ?',
                ['users', $this->db->skip()],
            ],
        ];
        foreach ($missedBracket as [$query, $args]) {
            $this->testNotClosedCondition($query, $args);
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

    /**
     * Тестирование выбрасывания исключения при незакрытом условном блоке запросе
     * @noinspection PhpRedundantCatchClauseInspection
     * @throws Exception
     */
    private function testNotClosedCondition(string $query, array $args): void
    {
        try {
            $query = $this->db->buildQuery($query, $args);
            $success = true;
        } catch (ConditionNotClosedException) {
            $success = false;
        } finally {
            if (!empty($success)) {
                throw new Exception("Missed [ConditionNotClosedException] exception. Query: $query");
            }
        }
    }
}