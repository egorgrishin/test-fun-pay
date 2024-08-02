<?php
declare(strict_types=1);

namespace FpDbTest\Queries;

use FpDbTest\Exceptions\ConditionNotClosedException;
use FpDbTest\Exceptions\MissedArgException;
use FpDbTest\Exceptions\QuotesNotClosedException;
use FpDbTest\Exceptions\ResolveArgException;

class QueryReplace extends AbstractQuery
{
    /** Длина строки query-запроса */
    private int  $queryLen;

    /** Индекс, с которого начинается условный блок */
    private ?int $startConditionIndex;

    /**
     * Возвращает отформатированную query строку
     *
     * @throws ResolveArgException
     * @throws MissedArgException
     * @throws ConditionNotClosedException
     * @throws QuotesNotClosedException
     */
    public function build(string $query, array $args): string
    {
        $this->updateProperties($query, $args);
        for ($this->cursor = 0; $this->cursor < $this->queryLen; $this->cursor++) {
            $curChar = $this->query[$this->cursor];

            match (true) {
                $this->inQuote                          => $this->handleText($curChar),
                $curChar === '"' || $curChar === '\''   => $this->handleStartText($curChar),
                $curChar === $this->paramChar           => $this->handleArgument(),
                !$this->inCondition && $curChar === '{' => $this->handleStartCondition(),
                $this->inCondition && $curChar === '}'  => $this->handleEndCondition(),
                default                                 => null,
            };
        }

        $this->checkCloseQuotes();
        $this->checkCloseCondition();
        return $this->query;
    }

    /**
     * Устанавливает значения свойств по умолчанию
     */
    private function updateProperties(string $query, array $args): void
    {
        $this->query = $query;
        $this->queryLen = strlen($query);
        $this->args = $args;
        $this->argIndex = 0;

        $this->quote = null;
        $this->inQuote = false;

        $this->startConditionIndex = null;
        $this->inCondition = false;
        $this->isSkip = false;
    }

    /**
     * Обрабатывает строку в кавычках
     */
    private function handleText(string $curChar): void
    {
        if ($this->quote === $curChar) {
            $this->quote = null;
            $this->inQuote = false;
            return;
        }

        if ($curChar !== '\\') {
            return;
        }

        $nextChar = $this->query[$this->cursor + 1] ?? null;
        if ($nextChar) {
            ++$this->cursor;
        }
    }

    /**
     * Обрабатывает начало строки (в query-запросе встретилась кавычка)
     */
    private function handleStartText(string $curChar): void
    {
        $this->inQuote = true;
        $this->quote = $curChar;
    }

    /**
     * Обрабатывает аргумент
     *
     * @throws MissedArgException
     * @throws ResolveArgException
     */
    private function handleArgument(): void
    {
        $arg = $this->getArg();
        if ($this->startConditionIndex !== null && ($this->isSkip || $arg instanceof ($this->skip()))) {
            $this->isSkip = true;
            return;
        }

        $nextChar = $this->query[$this->cursor + 1] ?? null;
        $arg = $this->formatArg($arg, $nextChar);

        $replaceLen = $this->isAuto($nextChar) ? 1 : 2;
        $this->query = substr_replace($this->query, $arg, $this->cursor, $replaceLen);
        $argLen = strlen($arg);

        $this->cursor += $argLen - 1;
        $this->queryLen += $argLen - $replaceLen;
    }

    /**
     * Обрабатывает начало условия
     */
    private function handleStartCondition(): void
    {
        $this->inCondition = true;
        $this->startConditionIndex = $this->cursor;
    }

    /**
     * Обрабатывает конец условия
     */
    private function handleEndCondition(): void
    {
        if ($this->isSkip) {
            $diff = $this->cursor - $this->startConditionIndex + 1;
            $this->query = substr_replace(
                $this->query, '', $this->startConditionIndex, $diff
            );
            $this->cursor -= $diff;
            $this->queryLen -= $diff;
        } else {
            $this->query = substr_replace($this->query, '', $this->startConditionIndex, 1);
            $this->query = substr_replace($this->query, '', $this->cursor - 1, 1);
            $this->cursor -= 1;
            $this->queryLen -= 2;
        }

        $this->inCondition = false;
        $this->startConditionIndex = null;
        $this->isSkip = false;
    }
}
