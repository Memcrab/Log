<?php

declare(strict_types=1);

namespace Memcrab\Log;

use Monolog\Handler\AbstractProcessingHandler;
use OpenSwoole\Coroutine\Http\Client;
use Monolog\Logger;
use Monolog\LogRecord;

class TelegrafHandler extends AbstractProcessingHandler
{
    private string $host;
    private int $port;

    
    public function __construct(string $telegrafUrl, int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $parsedUrl = parse_url($telegrafUrl);
        $this->host = $parsedUrl['host'] ?? 'localhost';
        $this->port = $parsedUrl['port'] ?? 8186;
    }

    protected function write(LogRecord $record): void
    {
        // Different logging approaches to efficiently handle Swoole’s coroutine context:
        // - Coroutine context: Use a non-blocking Swoole client for better performance (no waiting for response)
        // - Non-coroutine context (before Swoole server start or after it stops): Use a blocking request to ensure log delivery (print errors to stdout if sending fails)
        // - Non-coroutine context with coroutine hook enabled (e.g., logging inside `on('start')` callback): Logging must be wrapped in a coroutine in the main project code.
        $isRunningInCoroutine = $record->context['isRunningInCoroutine'] ?? false;
        if ($isRunningInCoroutine) {
            $this->sendInCoroutine($record->formatted);
        } else {
            $this->sendOutsideCoroutine($record->formatted);
        }
    }

    private function sendOutsideCoroutine(string $logEntry): void
    {
        $url = "http://{$this->host}:{$this->port}/api/v2/write";
    
        $ch = curl_init($url);
    
        $headers = [
            'Content-Type: text/plain; charset=utf-8', #default
        ];
    
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $logEntry,
            CURLOPT_HTTPHEADER => $headers,
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            fwrite(STDOUT, "Error while sending log via Telegraf Handler: cURL error - $error\n");
        } elseif ($httpCode >= 400) {
            fwrite(STDOUT, "Error while sending log via Telegraf Handler: HTTP $httpCode - Response: $response\n");
        }
    }
    
    private function sendInCoroutine(string $logEntry): void
    {
        $Client = new Client($this->host, $this->port);

        $headers = [
            'Content-Type' => 'text/plain; charset=utf-8', #default
        ];
        $Client->setHeaders($headers);

        $Client->post('/api/v2/write', $logEntry);
        $Client->close();
    }
}