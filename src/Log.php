<?php

declare(strict_types=1);

namespace Memcrab\Log;

use Monolog\Logger;
use Monolog\Handler\SqsHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;

class Log extends Logger
{

    private static array $context = [];
    private static Log $instance;

    public static function obj(): self
    {
        if (!isset(self::$instance) || !(self::$instance instanceof Logger)) {
            self::$instance = new self('log');

            \register_shutdown_function("\Memcrab\Log\Log::shutdown");

            # this part need to be implement with Monolog Signals Registration as a tool in monolog reop [14.06.22]
            // if(function_exists('pcntl_signal')) {
            //     pcntl_signal(SIGTERM, "\Memcrab\Log\Log::shutdown");
            //     pcntl_signal(SIGUSR1, "\Memcrab\Log\Log::shutdown");
            // } else {
            //     error_log("pcntl_signal not available please install pcntl php Module");
            // }
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

    public static function setServiceContext(
        string $project,
        string $service,
        string $environment,
        string $version,
        bool $DEBUG_MODE,
        string $hostname,
        string $timeZone = 'UTC'
    ): void {
        if ($timeZone !== 'UTC' && !self::isValidTimeZone($timeZone)) {
            trigger_error(sprintf('Invalid timezone: %s', $timeZone), E_USER_WARNING);
            $timeZone = 'UTC';
        }

        self::$context = [
            'project' => $project,
            'service' => $service,
            'environment' => $environment,
            'version' => $version,
            'DEBUG_MODE' => $DEBUG_MODE,
            'timeZone' => $timeZone,
            'hostname' => $hostname,
        ];
    }

    public static function getServiceContext()
    {
        return self::$context;
    }

    private function registerHandler($type, $value): void
    {
        switch ($type) {
            case 'StreamHandler':
                $Handler = new StreamHandler($value, Log::DEBUG);
                $Handler->setFormatter(new LineFormatter(null, null, true, true));
                break;
            case 'RotatingFileHandler':
                $Handler = new RotatingFileHandler($value, 5, Log::DEBUG);
                $Handler->setFormatter(new LineFormatter(null, null, true, true));
                break;
            case 'SqsHandler':
                $value->registerQueue('logs', ['ReceiveMessageWaitTimeSeconds' => 20]);
                $Handler = new SqsHandler($value->client(), $value->getQueueUrl('logs'));
                $Handler->setFormatter(new JsonFormatter());
                $Handler->pushProcessor('\Memcrab\Log\Log::contextProcessor');
                break;
            case 'TelegrafHandler':
                $Handler = new TelegrafHandler($value, Log::DEBUG);
                $Handler->setFormatter(new LineProtocolFormatter());
                $Handler->pushProcessor(new CoroutineContextProcessor());
                break;
            default:
                throw new \Exception($type . ' Handler scenario undefined. Please provide your own logic.', 500);
                break;
        }
        $this->pushHandler($Handler);
    }

    public function setLogHandlersBasedOnDebugMode(bool $DEBUG_MODE, array $debugHandlers, array $releaseHandler): void
    {
        foreach (($DEBUG_MODE) ? $debugHandlers : $releaseHandler as $key => $value) {
            $this->registerHandler($key, $value);
        }
    }

    private static function isValidTimeZone(string $timezone): bool 
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }
}
