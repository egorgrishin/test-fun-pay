<?php
declare(strict_types=1);

namespace FpDbTest;

interface DatabaseInterface
{
    /**
     * Возвращает сформированный SQL-запрос
     */
    public function buildQuery(string $query, array $args = []): string;

    /**
     * Возвращает значение для пропуска условного блока
     */
    public function skip(): mixed;
}
