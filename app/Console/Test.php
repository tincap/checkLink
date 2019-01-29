<?php

namespace app\Console;

use app\Console\Helpers\ConsoleHelpers;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

include __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../src/Autoloader.php';

class Test
{
    public function run()
    {
        $log = new Logger('');

        try {
            $log->pushHandler(new StreamHandler(__DIR__ . '/../../logs/errors.txt', Logger::WARNING));
        } catch (\Exception $e) {
            ConsoleHelpers::log($e->getMessage(), 31);
        }

        $log->warning('Не получилось', [
            'asd' => 'asd22'
        ]);
    }
}

$test = new Test();
$test->run();