<?php

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class LineProtocolFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        $measurement = 'log';
        
        //$level = strtolower($record->level->getName()); //type
        // $tagKeyMap = ['env' => 'env', 'instance' => 'host', 'job' => 'source', 'type' => 'type', 'version' => 'version', 'metricId' => 'metricId']; //MEG
        $tagKeyMap = ['env' => 'env', 'hostname' => 'host', 'service' => 'source', 'type' => 'type', 'version' => 'version']; //JG
        $tags = array_intersect_key($record->extra, $tagKeyMap);

        $tagsString = '';
        if(!empty($tags)) {
            $tagSet = [];
            foreach ($tags as $key => $value) {
                $mappedKey = $tagKeyMap[$key];
                $tagSet[$key] = $mappedKey . '=' . $value;
            }
            #before writing data points to InfluxDB, sort tags by key in lexicographic order.
            #https://docs.influxdata.com/influxdb/v2/write-data/best-practices/optimize-writes/#sort-tags-by-key
            ksort($tagSet);
            $tagsString = ',' . implode(',', $tagSet);
        }

        $fieldsString = 'logmessage=' . json_encode($record->message, JSON_UNESCAPED_UNICODE) . ',value=1';

        $now = \DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''))->setTimezone(new \DateTimeZone('Europe/Kiev'));
        $datetime = $now->format('Y-m-d\TH:i:s.uP');
        //part of the timestamp  in seconds
        $seconds = strtotime($datetime);
        //part of the timestamp  in microseconds
        $microseconds = substr($datetime, 0, strlen($datetime) - 6);
        $microseconds = substr($microseconds, -6);
        //timestamp in microseconds
        $timestampInMs= $seconds . $microseconds;

        return "{$measurement}{$tagsString} {$fieldsString} {$timestampInMs}";
    }

    private function validateTag(string $value): void
    {
        if (strpbrk($value, " ,") !== false) {
            throw new \InvalidArgumentException("Tag value '{$value}' contains invalid characters (spaces or commas).");
        }
    }
    
    private function validateField(string $value): void
    {
        if (strpbrk($value, "\"\n") !== false) {
            throw new \InvalidArgumentException("Field value '{$value}' contains an unescaped double quote (\") or newline.");
        }
    }

    public function formatBatch(array $records): string
    {
        return implode("\n", array_map([$this, 'format'], $records));
    }
}