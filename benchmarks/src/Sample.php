<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use JsonException;
use UnexpectedValueException;

/**
 * Measurement of one execution of the front controller (benchmarks/public/index.php)
 */
final readonly class Sample
{
    public const array PHASES = ['autoload', 'entryLine', 'container', 'bootstrap', 'firstCalls', 'steadyCalls', 'total'];

    /**
     * @param array<string, float> $phases Durations in milliseconds, keys of {@see PHASES}
     * @param array<string, bool|int> $loaded Subsystems loaded by the end of the request
     */
    public function __construct(
        public string $scenario,
        public bool $debug,
        public array $phases,
        public int $memoryPeak,
        public int $includedFiles,
        public bool $opcache,
        public array $loaded,
        public int $calls,
        public int $advices,
        public float $wallTime,
    ) {}

    /**
     * @param float $wallTime Duration measured by the caller (process or HTTP request), in milliseconds
     */
    public static function fromJson(string $json, float $wallTime): self
    {
        try {
            $data = json_decode(trim($json), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('The front controller printed no measurement: ' . $json, 0, $e);
        }
        if (!is_array($data) || !is_array($data['phases'] ?? null) || !is_array($data['loaded'] ?? null)) {
            throw new UnexpectedValueException('Malformed measurement: ' . $json);
        }
        $phases = [];
        foreach (self::PHASES as $phase) {
            $value = $data['phases'][$phase] ?? null;
            if (!is_float($value) && !is_int($value)) {
                throw new UnexpectedValueException(sprintf('Phase "%s" is missing: %s', $phase, $json));
            }
            $phases[$phase] = (float) $value;
        }
        $loaded = [];
        foreach ($data['loaded'] as $name => $value) {
            if (is_string($name) && (is_bool($value) || is_int($value))) {
                $loaded[$name] = $value;
            }
        }

        return new self(
            self::string($data, 'scenario'),
            ($data['debug'] ?? null) === true,
            $phases,
            self::int($data, 'memoryPeak'),
            self::int($data, 'includedFiles'),
            ($data['opcache'] ?? null) === true,
            $loaded,
            self::int($data, 'calls'),
            self::int($data, 'advices'),
            $wallTime,
        );
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : throw new UnexpectedValueException("\"{$key}\" is missing.");
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $key): int
    {
        return is_int($data[$key] ?? null) ? $data[$key] : throw new UnexpectedValueException("\"{$key}\" is missing.");
    }
}
