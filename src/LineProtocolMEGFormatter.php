<?php

declare(strict_types=1);

namespace Memcrab\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class LineProtocolMEGFormatter implements FormatterInterface
{
    protected string $tagsBeforeType;
    protected string $tagsAfterType;

    public function __construct(array $context)
    {
        if (isset($context['env'])) {
            $context['env'] = $this->normalizeTagValue($context['env']);
            $context['env'] = $this->escapeKey($context['env']);
        }
        if (isset($context['instance'])) {
            $context['instance'] = $this->normalizeTagValue($context['instance']);
            $context['instance'] = $this->escapeKey($context['instance']);
        }
        if (isset($context['job'])) {
            $context['job'] = $this->normalizeTagValue($context['job']);
            $context['job'] = $this->escapeKey($context['job']);
        }
        if (isset($context['version'])) {
            $context['version'] = $this->normalizeTagValue($context['version']);
            $context['version'] = $this->escapeKey($context['version']);
        }
        if (isset($context['metricId'])) {
            $context['metricId'] = $this->normalizeTagValue($context['metricId']);
            $context['metricId'] = $this->escapeKey($context['metricId']);
        }


        $this->tagsBeforeType =
            'env=' . ($context['env'] ?? 'empty')
            . ',host=' . ($context['instance'] ?? 'empty')
            . (isset($context['metricId']) ? ',metricId=' . $context['metricId'] : '')
            . ',source=' . ($context['job'] ?? 'empty');

        $this->tagsAfterType = 
            ',version=' . ($context['version'] ?? 'empty');
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

    public function format(LogRecord $record): string
    {
        $measurement = 'log';

        # For optimal performance, tags should be sorted lexicographically by key.
        # Reference: https://docs.influxdata.com/influxdb/v2/write-data/best-practices/optimize-writes/#sort-tags-by-key
        $tags = "{$this->tagsBeforeType},type={$record->level->getName()}{$this->tagsAfterType}";

        $fields = 
            'logmessage=' . json_encode($record->message, JSON_UNESCAPED_UNICODE) 
            . ',value=1'
        ;

        $now = \DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''))->setTimezone(new \DateTimeZone('Europe/Kiev'));
        $datetime = $now->format('Y-m-d\TH:i:s.uP');
        
        $seconds = strtotime($datetime); //part of the timestamp  in seconds
        $microseconds = substr($datetime, 0, strlen($datetime) - 6); //part of the timestamp  in microseconds
        $microseconds = substr($microseconds, -6);
        $timestampInMs = $seconds . $microseconds; //timestamp in microseconds

        //line protocol for influxDB (attention: space before field set and after)
        // measurementName,tagKey=tagValue fieldKey="fieldValue" 1465839830100400200
        // --------------- --------------- --------------------- -------------------
        //   Measurement       Tag set           Field set            Timestamp
        return "{$measurement},{$tags} {$fields} {$timestampInMs}";
    }

    public function formatBatch(array $records): string
    {
        return implode("\n", array_map([$this, 'format'], $records));
    }
}