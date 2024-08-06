<?php

use FpDbTest\DatabaseCopy;
use FpDbTest\DatabaseReplace;
use FpDbTest\Tests\DatabaseConditionTest;
use FpDbTest\Tests\DatabaseOperationsTest;
use FpDbTest\Tests\DatabaseQuotesTest;
use FpDbTest\Tests\DatabaseTest;
use FpDbTest\Tests\DatabaseTypesTest;

spl_autoload_register(function ($class) {
    $a = array_slice(explode('\\', $class), 1);
    if (!$a) {
        throw new Exception();
    }
    $filename = implode('/', [__DIR__, ...$a]) . '.php';
    require_once $filename;
});

$mysqli = @new mysqli('mysql', 'root', 'password', 'database', 3306);
if ($mysqli->connect_errno) {
    throw new Exception($mysqli->connect_error);
}

$dbs = [
    new DatabaseCopy($mysqli),
    new DatabaseReplace($mysqli),
];

foreach ($dbs as $db) {
    (new DatabaseOperationsTest($db))->test();
    (new DatabaseTest($db))->test();
    (new DatabaseTypesTest($db))->test();
    (new DatabaseQuotesTest($db))->test();
    (new DatabaseConditionTest($db))->test();
}

exit('OK' . PHP_EOL);
