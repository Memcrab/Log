<?php

declare(strict_types=1);

namespace Memcrab\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class LineProtocolFormatter implements FormatterInterface
{
    protected string $tagsBeforeSource;
    protected string $tagSource = '';
    protected string $fieldVersion;

    public function __construct()
    {
        $context = Log::getServiceContext();
        
        if (isset($context['environment'])) {
            $context['environment'] = $this->normalizeTagValue($context['environment']);
            $context['environment'] = $this->escapeKey($context['environment']);
        }

        if (isset($context['hostname'])) {
            $context['hostname'] = $this->normalizeTagValue($context['hostname']);
            $context['hostname'] = $this->escapeKey($context['hostname']);
        }

        if (isset($context['service'])) {
            $context['service'] = $this->normalizeTagValue($context['service']);
            $context['service'] = $this->escapeKey($context['service']);
            $this->tagSource = $context['service'];
        }
        
        if (isset($context['version'])) {
            $this->fieldVersion = $this->escapeValue($context['version']);
        }

        $this->tagsBeforeSource =
            'env=' . ($context['environment'] ?? 'empty')
            . ',host=' . ($context['hostname'] ?? 'empty');
    }

    private function normalizeTagValue($value): string
    {
        if (!is_string($value) && null !== $value) {
            if (
                is_scalar($value) 
                || (is_object($value) && method_exists($value, '__toString')) 
                || (\PHP_VERSION_ID >= 80000 && $value instanceof \Stringable)
            ) {
                return (string) $value;
            } else {
                trigger_error(sprintf('Tag value cannot be converted to string: %s', gettype($value)), E_USER_WARNING);
                return 'empty';
            }
        }

        return $value;
    }

    private function escapeKey($key, $escapeEqual = true)
    {
        $escapeKeys = [
            ' ' => '\\ ', 
            ',' => '\\,', 
            "\\" => '\\\\',
            "\n" => '\\n', 
            "\r" => '\\r', 
            "\t" => '\\t'
        ];

        if ($escapeEqual) {
            $escapeKeys['='] = '\\=';
        }

        return strtr($key, $escapeKeys);
    }

    private function escapeValue($value)
    {
        $escapeValues = ['"' => '\\"', "\\" => '\\\\'];
        return strtr($value, $escapeValues);
    }

    public function format(LogRecord $record): string
    {
        $measurement = 'log';

        if(isset($record['context']['service'])) {
            $recordContextService = $this->normalizeTagValue($record['context']['service']);
            $recordContextService = $this->escapeKey($record['context']['service']);
            $tagSource = $recordContextService ?? 'empty';
        } else {
            $tagSource = $this->tagSource;
        }

        # For optimal performance, tags should be sorted lexicographically by key.
        # Reference: https://docs.influxdata.com/influxdb/v2/write-data/best-practices/optimize-writes/#sort-tags-by-key
        $tags = "{$this->tagsBeforeSource},source={$tagSource},type={$record->level->getName()}";

        $timeZone = new \DateTimeZone(Log::getServiceContext()['timeZone'] ?? 'UTC');
        $now = \DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''))->setTimezone($timeZone);
        $datetime = $now->format('Y-m-d\TH:i:s.uP');

        $logmessage = $record->message . " [$tagSource:$datetime]";
        $fields = 'logmessage="' . $this->escapeValue($logmessage) . '",value=1,version="' . $this->fieldVersion. '"';
        
        $seconds = strtotime($datetime); //part of the timestamp  in seconds
        $microseconds = substr($datetime, 0, strlen($datetime) - 6); //part of the timestamp  in microseconds
        $microseconds = substr($microseconds, -6);
        $timestampInNs = $seconds . $microseconds . '000';

        #line protocol for influxDB (attention: space before field set and after)
        # measurementName,tagKey=tagValue fieldKey="fieldValue" 1465839830100400200
        # --------------- --------------- --------------------- -------------------
        #   Measurement       Tag set           Field set            Timestamp
        return "{$measurement},{$tags} {$fields} {$timestampInNs}";
    }

    public function formatBatch(array $records): string
    {
        return implode("\n", array_map([$this, 'format'], $records));
    }
}