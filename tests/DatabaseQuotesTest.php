<?php
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

namespace FpDbTest\Tests;

use Exception;
use FpDbTest\DatabaseInterface;
use FpDbTest\Exceptions\QuotesNotClosedException;

class DatabaseQuotesTest
{
    public function __construct(
        private DatabaseInterface $db,
    ) {}

    /**
     * Проверяет корректную работу со строками в кавычках
     * @throws Exception
     */
    public function test(): void
    {
        $cases = [
            // Просто строка в кавычках
            [
                'SELECT `id`, "test str" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test str" FROM `users` WHERE `id` = 10',
            ],
            // Строку в кавычках считаем просто строкой, параметры не подставляем
            [
                'SELECT `id`, "test ? ?d str" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test ? ?d str" FROM `users` WHERE `id` = 10',
            ],
            // Одинарная кавычка в двойных кавычках - ничего особенного
            [
                'SELECT `id`, "test ? \' ?d str" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test ? \' ?d str" FROM `users` WHERE `id` = 10',
            ],
            // Обратный слэш и одинарная кавычка - тоже ничего особенного
            [
                'SELECT `id`, "test ? \\\' ?d str" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test ? \\\' ?d str" FROM `users` WHERE `id` = 10',
            ],
            // Экранированную кавычку за конец строки не считаем
            [
                'SELECT `id`, "test ? \" ?d str" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test ? \" ?d str" FROM `users` WHERE `id` = 10',
            ],
            // Экранирование сработало правильно,
            [
                'SELECT `id`, "test ? \\\\\" ?d str" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test ? \\\\\" ?d str" FROM `users` WHERE `id` = 10',
            ],
            // Допустимо - кавычка сама себя экранирует
            [
                'SELECT `id`, "test s""r" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test s""r" FROM `users` WHERE `id` = 10',
            ],
            // Аналогично
            [
                'SELECT `id`, "test st""""r" FROM ?# WHERE `id` = ?d',
                ['users', 10],
                'SELECT `id`, "test st""""r" FROM `users` WHERE `id` = 10',
            ],
        ];
        foreach ($cases as [$query, $args, $excepted]) {
            $result = $this->db->buildQuery($query, $args);
            if ($result !== $excepted) {
                $this->throwBuildError($result, $excepted);
            }
        }

        $missedQuote = [
            [
                'SELECT `id`, "test st"r" FROM ?# WHERE `id` = ?d',
                ['users', 10],
            ],
            [
                'SELECT `id`, "test st"""r" FROM ?# WHERE `id` = ?d',
                ['users', 10],
            ],
            [
                'SELECT `id`, "test st\\\"r" FROM ?# WHERE `id` = ?d',
                ['users', 10],
            ],
            [
                'SELECT `id`, "test st\\\\"r" FROM ?# WHERE `id` = ?d',
                ['users', 10],
            ],
        ];
        foreach ($missedQuote as [$query, $args]) {
            $this->testNotClosedQuotes($query, $args);
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
     * Тестирование выбрасывания исключения при незакрытых кавычках в запросе
     * @noinspection PhpRedundantCatchClauseInspection
     * @throws Exception
     */
    private function testNotClosedQuotes(string $query, array $args): void
    {
        try {
            $query = $this->db->buildQuery($query, $args);
            $success = true;
        } catch (QuotesNotClosedException) {
            $success = false;
        } finally {
            if (!empty($success)) {
                throw new Exception("Missed [QuotesNotClosedException] exception. Query: $query");
            }
        }
    }
}