<?php
declare(strict_types=1);

namespace FpDbTest;

use FpDbTest\Exceptions\ConditionNotClosedException;
use FpDbTest\Exceptions\MissedArgException;
use FpDbTest\Exceptions\QuotesNotClosedException;
use FpDbTest\Exceptions\ResolveArgException;
use FpDbTest\Queries\AbstractQuery;
use FpDbTest\Queries\QueryReplace;
use FpDbTest\Queries\SkipArg;
use mysqli;

class DatabaseReplace implements DatabaseInterface
{
    private mysqli        $mysqli;
    private AbstractQuery $query;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->query = new QueryReplace($this->mysqli);
    }

    /**
     * Возвращает сформированный SQL-запрос
     *
     * @throws ConditionNotClosedException
     * @throws ResolveArgException
     * @throws QuotesNotClosedException
     * @throws MissedArgException
     */
    public function buildQuery(string $query, array $args = []): string
    {
        return $this->query->build($query, $args);
    }

    /**
     * Возвращает значение для пропуска условного блока
     */
    public function skip(): SkipArg
    {
        return $this->query->skip();
    }
}
