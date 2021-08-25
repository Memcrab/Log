<?php declare (strict_types = 1);
namespace Memcrab\Log;

use Aws\Sqs\SqsClient;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AmqpHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SqsHandler;
use Monolog\Logger;
use PhpAmqpLib\Channel\AMQPChannel;

/**
 *  Log for core project
 *
 *  @author Oleksandr Diudiun
 */
class Log {

    private static $instance;
    private static $context = [];

    function __construct() {}

    public static function stream($name) {
        if (!isset(self::$instance[$name])) {
            self::$instance[$name] = new Logger($name);
        }

        return self::$instance[$name];
    }

    public static function error($name, $message) {
        if(empty(self::$instance[$name])){
            die($message);
        }
        self::$instance[$name]->error($message, self::$context);
    }
    
    public static function info($name, $message) {
        if(empty(self::$instance[$name])){
            die($message);
        }
        self::$instance[$name]->info($message, self::$context);
    }

    public static function setContext(array $context) {
        self::$context = $context;
    }

    public static function setDefaultRotationHandler($name) {
        self::stream($name)->pushHandler(new RotatingFileHandler('logs/' . $name . '/' . $name . '.log'));
    }

    public static function setSqsHandler(SqsClient $sqsClient, $queueUrl, $name) {
        $stream = new SqsHandler($sqsClient, $queueUrl);
        $stream->setFormatter(new JsonFormatter());
        self::stream($name)->pushHandler($stream);
    }

    public static function setAmqpHandler(AMQPChannel $channel, $exchangeName, $name) {
        $stream = new AmqpHandler($channel, $exchangeName);
        $stream->setFormatter(new JsonFormatter());
        self::stream($name)->pushHandler($stream);
    }
}
