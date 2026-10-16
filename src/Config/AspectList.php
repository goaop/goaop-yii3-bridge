<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Config;

use ArrayIterator;
use Countable;
use Go\Aop\Aspect;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Aspect classes declared in the "aspects" parameter of the bridge
 *
 * The list is built from the merged application parameters, so every package can contribute its own aspects. The
 * class names are only checked syntactically: nothing is autoloaded here, aspects stay unloaded until the weaver or
 * one of their advices needs them. Duplicates (e.g. contributed by two packages) are registered once.
 *
 * @implements IteratorAggregate<int, class-string>
 */
final readonly class AspectList implements IteratorAggregate, Countable
{
    private const string CLASS_NAME_PATTERN = '/^[A-Za-z_\x80-\xff][\w\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)*$/';

    /**
     * @param list<class-string> $aspectClasses
     */
    private function __construct(private array $aspectClasses) {}

    /**
     * @param array<mixed> $params Value of $params['goaop/goaop-yii3-bridge']
     */
    public static function fromParams(array $params): self
    {
        $aspects = $params['aspects'] ?? [];
        if (!is_array($aspects)) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" parameter "aspects" must be a list of aspect class names, %s given.',
                KernelOptions::PARAMS_KEY,
                get_debug_type($aspects),
            ));
        }

        return self::fromClassNames($aspects);
    }

    /**
     * @param array<mixed> $classNames
     */
    public static function fromClassNames(array $classNames): self
    {
        $aspectClasses = [];
        foreach ($classNames as $className) {
            if (!is_string($className) || preg_match(self::CLASS_NAME_PATTERN, ltrim($className, '\\')) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'The "%s" parameter "aspects" must contain aspect class names, %s given.',
                    KernelOptions::PARAMS_KEY,
                    is_string($className) ? '"' . $className . '"' : get_debug_type($className),
                ));
            }
            /** @var class-string $className Syntactically valid, deliberately not autoloaded */
            $className = ltrim($className, '\\');
            $aspectClasses[$className] = $className;
        }

        return new self(array_values($aspectClasses));
    }

    /**
     * Ensures every listed class exists and implements {@see Aspect}
     *
     * Autoloads the aspect classes, so it is meant for the debug mode only: the aspect container silently ignores
     * a registered service that is not an aspect, this check turns such a misconfiguration into an error.
     *
     * @throws InvalidArgumentException
     */
    public function assertAspects(): void
    {
        foreach ($this->aspectClasses as $aspectClass) {
            if (!class_exists($aspectClass)) {
                throw new InvalidArgumentException(sprintf(
                    'Aspect class "%s" listed in the "%s" parameter "aspects" does not exist.',
                    $aspectClass,
                    KernelOptions::PARAMS_KEY,
                ));
            }
            if (!is_subclass_of($aspectClass, Aspect::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Class "%s" listed in the "%s" parameter "aspects" must implement the "%s" interface.',
                    $aspectClass,
                    KernelOptions::PARAMS_KEY,
                    Aspect::class,
                ));
            }
        }
    }

    /**
     * @return list<class-string>
     */
    public function toArray(): array
    {
        return $this->aspectClasses;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->aspectClasses);
    }

    public function count(): int
    {
        return count($this->aspectClasses);
    }
}
