<?php
declare(strict_types=1);

namespace FpDbTest\Queries;

use FpDbTest\Exceptions\ConditionNotClosedException;
use FpDbTest\Exceptions\MissedArgException;
use FpDbTest\Exceptions\QuotesNotClosedException;
use FpDbTest\Exceptions\ResolveArgException;

class QueryCopy extends AbstractQuery
{
    /** Ссылка на текстовую переменную, в которую записывается query запрос */
    private string $text;

    /** Результирующий текст */
    private string $result;

    /** Текст условия */
    private ?string $condition;

    /**
     * Возвращает отформатированную query строку
     *
     * @throws MissedArgException
     * @throws ResolveArgException
     * @throws QuotesNotClosedException
     * @throws ConditionNotClosedException
     */
    public function build(string $query, array $args): string
    {
        $this->updateProperties($query, $args);
        $queryLen = strlen($this->query);
        for ($this->cursor = 0; $this->cursor < $queryLen; $this->cursor++) {
            $curChar = $this->query[$this->cursor];

            match (true) {
                $this->inQuote                          => $this->handleText($curChar),
                $curChar === '"' || $curChar === '\''   => $this->handleStartText($curChar),
                $curChar === $this->paramChar           => $this->handleArgument(),
                !$this->inCondition && $curChar === '{' => $this->handleStartCondition(),
                $this->inCondition && $curChar === '}'  => $this->handleEndCondition(),
                default                                 => $this->text .= $curChar,
            };
        }

        $this->checkCloseQuotes();
        $this->checkCloseCondition();
        return $this->result;
    }

    /**
     * Устанавливает значения свойств по умолчанию
     */
    private function updateProperties(string $query, array $args): void
    {
        $this->query = $query;
        $this->args = $args;
        $this->argIndex = 0;

        $this->quote = null;
        $this->inQuote = false;

        $this->result = '';
        $this->text = &$this->result;
        $this->condition = null;
        $this->inCondition = false;
        $this->isSkip = false;
    }

    /**
     * Обрабатывает строку в кавычках
     */
    private function handleText(string $curChar): void
    {
        $this->text .= $curChar;
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
            $this->text .= $nextChar;
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
        $this->text .= $curChar;
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
        if ($this->inCondition && ($this->isSkip || $arg instanceof ($this->skip()))) {
            $this->isSkip = true;
            return;
        }

        $nextChar = $this->query[$this->cursor + 1] ?? null;
        $arg = $this->formatArg($arg, $nextChar);
        if (!$this->isAuto($nextChar)) {
            ++$this->cursor;
        }

        $this->text .= $arg;
    }

    /**
     * Обрабатывает начало условия
     */
    private function handleStartCondition(): void
    {
        $this->inCondition = true;
        $this->condition = '';
        $this->text = &$this->condition;
    }

    /**
     * Обрабатывает конец условия
     */
    private function handleEndCondition(): void
    {
        $this->text = &$this->result;
        if ($this->isSkip) {
            $this->condition = null;
            $this->isSkip = false;
        }
        $this->text .= $this->condition;
        $this->inCondition = false;
    }
}
