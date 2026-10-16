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
 * Aspect classes declared in the "aspects" parameter of the bridge or tagged in the container config
 *
 * The list is built from the merged application config, so every package can contribute its own aspects. The
 * class names are only checked syntactically: nothing is autoloaded here, aspects stay unloaded until the weaver or
 * one of their advices needs them. Duplicates (e.g. contributed by two packages) are registered once.
 *
 * @implements IteratorAggregate<int, class-string>
 */
final readonly class AspectList implements IteratorAggregate, Countable
{
    private const string CLASS_NAME_PATTERN = '/^[A-Za-z_\x80-\xff][\w\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)*$/';

    /**
     * Where the aspects of the "aspects" parameter are declared, as used in error messages
     */
    private const string PARAMS_ORIGIN = 'the "' . KernelOptions::PARAMS_KEY . '" parameter "aspects"';

    /**
     * @param array<class-string, string> $origins Declaration place of every aspect class, in registration order
     */
    private function __construct(private array $origins) {}

    /**
     * @param array<mixed> $params Value of $params['goaop/goaop-yii3-bridge']
     */
    public static function fromParams(array $params): self
    {
        $aspects = $params['aspects'] ?? [];
        if (!is_array($aspects)) {
            throw new InvalidArgumentException(sprintf(
                '%s must be a list of aspect class names, %s given.',
                ucfirst(self::PARAMS_ORIGIN),
                get_debug_type($aspects),
            ));
        }

        return self::fromClassNames($aspects);
    }

    /**
     * @param array<mixed> $classNames
     * @param string       $origin     Where the classes are declared, for error messages
     */
    public static function fromClassNames(array $classNames, string $origin = self::PARAMS_ORIGIN): self
    {
        $origins = [];
        foreach ($classNames as $className) {
            if (!is_string($className) || preg_match(self::CLASS_NAME_PATTERN, ltrim($className, '\\')) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    '%s must contain aspect class names, %s given.',
                    ucfirst($origin),
                    is_string($className) ? '"' . $className . '"' : get_debug_type($className),
                ));
            }
            /** @var class-string $className Syntactically valid, deliberately not autoloaded */
            $className = ltrim($className, '\\');
            $origins[$className] ??= $origin;
        }

        return new self($origins);
    }

    /**
     * Returns the aspects of this list followed by the ones of the other list that are not listed yet
     */
    public function merge(self $other): self
    {
        return new self($this->origins + $other->origins);
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
        foreach ($this->origins as $aspectClass => $origin) {
            if (!class_exists($aspectClass)) {
                throw new InvalidArgumentException(sprintf(
                    'Aspect class "%s" listed in %s does not exist.',
                    $aspectClass,
                    $origin,
                ));
            }
            if (!is_subclass_of($aspectClass, Aspect::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Class "%s" listed in %s must implement the "%s" interface.',
                    $aspectClass,
                    $origin,
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
        return array_keys($this->origins);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    public function count(): int
    {
        return count($this->origins);
    }
}
