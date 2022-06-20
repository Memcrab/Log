<?php

declare(strict_types=1);

namespace Memcrab\Log;

use Monolog\Logger;

class Log extends Logger
{

    private static array $context = [];
    private static Log $instance;

    public static function obj(): self
    {
        if (!isset(self::$instance) || !(self::$instance instanceof Log)) {
            self::$instance = new self('log');
        }
        return self::$instance;
    }

    public static function shutdown($signo = null): void
    {
        $error = "Server shuted down. " . $signo . " <" . json_encode(error_get_last()) . ">";
        self::$instance->error($error);
    }

    public static function contextProcessor($record)
    {
        $record['extra'] = self::$context;
        return $record;
    }

    public static function registerShutdownFunction(array $additionalShutdownFunctions = []): void
    {
        foreach ($additionalShutdownFunctions as $function) {
            register_shutdown_function($function);
        }
        register_shutdown_function("\Memcrab\Log\Log::shutdown");

        # this part need to be implement with Monolog Signals Registration as a tool in monolog reop [14.06.22]
        // if(function_exists('pcntl_signal')) {
        //     pcntl_signal(SIGTERM, "\Memcrab\Log\Log::shutdown");
        //     pcntl_signal(SIGUSR1, "\Memcrab\Log\Log::shutdown");
        // } else {
        //     error_log("pcntl_signal not available please install pcntl php Module");
        // }
    }

    public static function setServiceContext(
        string $project,
        string $service,
        string $environment,
        bool $DEBUG_MODE,
        string $hostname,
        string $ip,
        string $os
    ): void {
        self::$context = [
            'project' => $project,
            'service' => $service,
            'environment' => $environment,
            'DEBUG_MODE' => $DEBUG_MODE,
            'hostname' => $hostname,
            'ip' => $ip,
            'os' => $os
        ];
    }
}
