<?php

use FpDbTest\DatabaseCopy;
use FpDbTest\DatabaseReplace;

/**
 * Сравнение производительности обоих решений
 */

spl_autoload_register(function ($class) {
    $a = array_slice(explode('\\', $class), 1);
    if (!$a) {
        throw new Exception();
    }
    $filename = implode('/', [__DIR__ . '/..', ...$a]) . '.php';
    require_once $filename;
});

$mysqli = new mysqli('mysql', 'root', 'password', 'database', 3306);
if ($mysqli->connect_errno) {
    throw new Exception($mysqli->connect_error);
}

$dbs = [
    'copy'    => new DatabaseCopy($mysqli),
    'replace' => new DatabaseReplace($mysqli),
];
$iterations = 10000;
$s = "";

foreach ($dbs as $key => $db) {
    $start = microtime(true);
    for ($i = 0; $i < $iterations; ++$i) {
        $db->buildQuery('SELECT name FROM users WHERE user_id = 1');

        $db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = 0',
            ['Jack']
        );

        $db->buildQuery(
            'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
            [['name', 'email'], 2, true]
        );

        $db->buildQuery(
            'UPDATE users SET ?a WHERE user_id = -1',
            [['name' => 'Jack', 'email' => null]]
        );

        foreach ([null, true] as $block) {
            $db->buildQuery(
                'SELECT name FROM users WHERE ?# IN (?a){ AND block = ?d}',
                ['user_id', [1, 2, 3], $block ?? $db->skip()]
            );
        }
    }
    $time = microtime(true) - $start;
    $s .= "$key - $time" . PHP_EOL;
}

file_put_contents(__DIR__ . '/db_test.txt', $s);
