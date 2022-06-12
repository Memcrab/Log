<?php declare (strict_types = 1);
namespace Memcrab\Log;

use Monolog\Logger;

/**
 *  Log for core project
 *
 *  @author Oleksandr Diudiun
 */
class Log extends Logger {

    // https://github.com/Seldaek/monolog/blob/main/src/Monolog/Registry.php

    // особенно callStatic

    private static $context = [];
    
    public static function registerShutdownFunction(array $additionalShutdownFunctions = []):void {
        foreach($additionalShutdownFunctions as $function) {
            register_shutdown_function($function);
        }
        register_shutdown_function("Log::shutdown");
        
        pcntl_signal(SIGTERM, function($signo) {
            Log::shutdown($signo);
        });
    }

    public static function shutdown($signo = null):void {
        $error = "Server stopped: " . ($signo ?? json_encode(error_get_last()));
        error_log($error);
        exit($error);
    }

    public function error($message) {
        self::error($message, self::$context);
    }

    public function warning($message) {
        self::warning($message, self::$context);
    }

    public static function setContext(array $context):void {
        self::$context = $context;
    }
}
