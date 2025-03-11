<?php

use Monolog\Handler\AbstractProcessingHandler;
use OpenSwoole\Coroutine\Http\Client;
use Monolog\Logger;
use Monolog\LogRecord;

class TelegrafHandler extends AbstractProcessingHandler
{
    private string $host;
    private int $port;
    private string $authToken;

    
    public function __construct(string $telegrafUrl, string $authToken = '', int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $parsedUrl = parse_url($telegrafUrl);
        $this->host = $parsedUrl['host'] ?? 'localhost';
        $this->port = $parsedUrl['port'] ?? 8186;
        $this->authToken = $authToken;
        // $this->setFormatter(new LineProtocolFormatter());
    }

    protected function write(LogRecord $record): void
    {
        print_r(['record.formatted' => $record->formatted]);
        // $this->send($record->formatted);
    }

    private function send(string $logEntry): void
    {
        $Client = new Client($this->host, $this->port);

        $headers = [
            'Content-Type' => 'text/plain; charset=utf-8', #default
            'Accept' => 'application/json', #default
        ];
        if (!empty($this->authToken)) {
            $headers['Authorization'] = 'Token ' . $this->authToken;
        }
        $Client->setHeaders($headers);

        $Response = $Client->post('/api/v2/write', $logEntry);
        $Client->close();
    }
}