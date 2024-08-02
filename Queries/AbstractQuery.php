<?php
declare(strict_types=1);

namespace FpDbTest\Queries;

use FpDbTest\Exceptions\ConditionNotClosedException;
use FpDbTest\Exceptions\MissedArgException;
use FpDbTest\Exceptions\QuotesNotClosedException;
use FpDbTest\Exceptions\ResolveArgException;
use mysqli;
use Throwable;

abstract class AbstractQuery
{
    private mysqli $mysqli;

    /** Строка query запроса */
    protected string $query;

    /** Аргументы query запроса */
    protected array $args;

    /** Индекс, указывающий на текущий элемент */
    protected int $argIndex;

    /** Индекс, указывающий на текущий символ query строки */
    protected int $cursor;

    /** С какой кавычки началась строка */
    protected ?string $quote;

    /** Находится ли курсор внутри строки */
    protected bool $inQuote;

    /** Находится ли курсор внутри условного блока */
    protected bool $inCondition;

    /** Нужно ли будет пропустить условный блок */
    protected bool $isSkip;

    /** Символ, с которого начинается параметр */
    protected string $paramChar = '?';

    /**
     * Спецификаторы преобразования.
     * Ключ массива - символ спецификатора.
     * Значение - функция, которая преобразует параметры в соответствии со спецификатором.
     *
     * @var array<string, callable(mixed): string>
     */
    private array $specifiers;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->setSpecifiers();
    }

    /**
     * Устанавливает список спецификаторов с их методами-преобразователями
     */
    protected function setSpecifiers(): void
    {
        $this->specifiers = [
            'd' => fn ($n) => $this->resolveInt($n),
            'f' => fn ($n) => $this->resolveFloat($n),
            'a' => fn ($n) => $this->resolveArray($n),
            '#' => fn ($n) => $this->resolveId($n),
        ];
    }

    /**
     * Возвращает сформированный SQL-запрос
     */
    abstract public function build(string $query, array $args): string;

    /**
     * Возвращает значение для пропуска условного блока
     */
    public function skip(): SkipArg
    {
        return new SkipArg();
    }

    /**
     * Возвращает аргумент по индексу
     * @throws MissedArgException
     */
    protected function getArg(): mixed
    {
        if (!array_key_exists($this->argIndex, $this->args)) {
            throw new MissedArgException($this->argIndex);
        }
        return $this->args[$this->argIndex++];
    }

    /**
     * Форматирует аргумент в соответствии со спецификатором
     * @throws ResolveArgException
     */
    protected function formatArg(mixed $arg, ?string $modifier): string
    {
        if (array_key_exists($modifier, $this->specifiers)) {
            $resolver = $this->specifiers[$modifier];
            return $resolver($arg);
        }
        return $this->resolveDefault($arg);
    }

    /**
     * Определяет наличие спецификатора
     */
    protected function isAuto(?string $modifier): bool
    {
        return !array_key_exists($modifier, $this->specifiers);
    }

    /**
     * Форматирует аргумент в целое число или NULL
     * @throws ResolveArgException
     */
    protected function resolveInt(mixed $arg): string
    {
        return match (true) {
            is_numeric($arg) => (string) (int) $arg,
            is_null($arg)    => 'NULL',
            is_bool($arg)    => $arg ? '1' : '0',
            default          => throw new ResolveArgException($this->argIndex - 1, 'int'),
        };
    }

    /**
     * Форматирует аргумент в вещественное число или NULL
     * @throws ResolveArgException
     */
    protected function resolveFloat(mixed $arg): string
    {
        return match (true) {
            is_numeric($arg) => (string) (float) $arg,
            is_null($arg)    => 'NULL',
            is_bool($arg)    => $arg ? '1' : '0',
            default          => throw new ResolveArgException($this->argIndex - 1, 'float'),
        };
    }

    /**
     * Форматирует аргумент в список значений через запятую, если аргумент является списком.
     * В противном случае форматирует в пары идентификатор + значение через запятую.
     * @throws ResolveArgException
     */
    protected function resolveArray(mixed $arg): string
    {
        if (!is_array($arg)) {
            throw new ResolveArgException($this->argIndex - 1, 'list');
        }

        if (array_is_list($arg)) {
            $argsCount = count($arg);
            for ($i = 0; $i < $argsCount; $i++) {
                $arg[$i] = $this->resolveDefault($arg[$i]);
            }
        } else {
            foreach ($arg as $key => $value) {
                $arg[$key] = $this->resolveId([$key]) . ' = ' . $this->resolveDefault($value);
            }
        }

        return implode(', ', $arg);
    }

    /**
     * Форматирует аргумент в одиночный идентификатор или массив идентификаторов
     * @throws ResolveArgException
     */
    protected function resolveId(mixed $arg): string
    {
        $arg = is_array($arg) ? $arg : [$arg];
        try {
            foreach ($arg as $key => $value) {
                if (is_scalar($value)) $value = (string) $value;
                $arg[$key] = $this->escapeId($value);
            }
            return implode(', ', $arg);
        } catch (Throwable) {
            throw new ResolveArgException($this->argIndex - 1, 'id');
        }
    }

    /**
     * Автоматически форматирует аргумент в соответствии с его типом
     * @throws ResolveArgException
     */
    protected function resolveDefault(mixed $arg): string
    {
        return match (true) {
            is_string($arg)                => $this->escapeString($arg),
            is_int($arg) || is_float($arg) => (string) $arg,
            is_bool($arg)                  => $arg ? '1' : '0',
            is_null($arg)                  => 'NULL',
            default                        => throw new ResolveArgException($this->argIndex - 1, 'default'),
        };
    }

    /**
     * Экранирует строки для запроса
     */
    private function escapeString(string $string): string
    {
        return '\'' . $this->mysqli->real_escape_string($string) . '\'';
    }

    /**
     * Экранирует идентификаторы для запроса
     */
    private function escapeId(string $string): string
    {
        $string = str_replace('`', '``', $string);
        return '`' . $this->mysqli->real_escape_string($string) . '`';
    }

    /**
     * Проверяет, что кавычки, в которых находится строка, закрыты
     * @throws QuotesNotClosedException
     */
    protected function checkCloseQuotes(): void
    {
        if ($this->inQuote) {
            throw new QuotesNotClosedException();
        }
    }

    /**
     * Проверяет, что фигурные скобки условия закрыты
     * @throws ConditionNotClosedException
     */
    protected function checkCloseCondition(): void
    {
        if ($this->inCondition) {
            throw new ConditionNotClosedException();
        }
    }
}