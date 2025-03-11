<?php

declare(strict_types=1);

namespace Memcrab\Log;

use LineProtocolFormatter;
use Monolog\Logger;
use Monolog\Handler\SqsHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;
use TelegrafHandler;

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
        bool $DEBUG_MODE
    ): void {
        self::$context = [
            'project' => $project,
            'service' => $service,
            'environment' => $environment,
            'DEBUG_MODE' => $DEBUG_MODE,
            'hostname' => gethostname(),
            'ip' =>  gethostbyname(gethostname()),
            'os' => php_uname('s') . " " . php_uname('r')
        ];
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
                $Handler = new TelegrafHandler($value, '', Log::DEBUG);
                $Handler->pushProcessor('\Memcrab\Log\Log::contextProcessor');
                $Handler->setFormatter(new LineProtocolFormatter());
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
}
