<?php

declare(strict_types=1);

namespace Memcrab\Log;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use OpenSwoole\Coroutine;

class CoroutineContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        # Coroutine::getCid() > 0 - The code is running inside a coroutine,
        # Coroutine::getCid() == -1 - The code is running in the main thread (outside of a coroutine)
        $isRunningInCoroutine = Coroutine::getCid() > 0;

        return $record->with(context: [...$record->context, 'isRunningInCoroutine' => $isRunningInCoroutine]);
    }
}