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

use InvalidArgumentException;
use Yiisoft\Config\ConfigInterface;

/**
 * Collects the aspects tagged in the container configuration, without creating any service
 *
 * The Yii container can only return tagged services by creating all of them, so the tag declarations are read
 * from the configuration instead, in the two formats the container accepts:
 *
 * - the "tags" meta key of a definition in the "di" group:
 *   `LoggingAspect::class => ['tags' => ['goaop.aspect']]` or `['definition' => ..., 'tags' => ['goaop.aspect']]`;
 * - a tag map in the "di-tags" group: `['goaop.aspect' => [LoggingAspect::class]]`.
 *
 * Only these common groups are read: web and console processes share the woven cache, so they must weave the same
 * aspects. Tags of web or console specific groups and of service providers are not seen.
 *
 * Opt-in (the "aspectTag" parameter is null by default): the groups are PHP, evaluated before the aspect kernel
 * starts, so every class their definitions reference (a class constant, an enum case) is loaded too early to be
 * woven. The "aspects" parameter has no such limitation.
 */
final readonly class TaggedAspects
{

    /**
     * Group of the container definitions, the "tags" meta key of every definition is read
     */
    public const string DEFINITIONS_GROUP = 'di';

    /**
     * Group of the tag map given to the container, ['tag' => [service ids]]
     */
    public const string TAGS_GROUP = 'di-tags';

    /**
     * @param string|null $tag Tag of the aspect services, e.g. "goaop.aspect"; null disables the collection
     */
    public function __construct(private ?string $tag) {}

    /**
     * @param array<mixed> $params Value of $params['goaop/goaop-yii3-bridge']
     */
    public static function fromParams(array $params): self
    {
        $tag = $params['aspectTag'] ?? null;
        if ($tag !== null && (!is_string($tag) || $tag === '')) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" parameter "aspectTag" must be a non-empty tag name or null, %s given.',
                KernelOptions::PARAMS_KEY,
                get_debug_type($tag),
            ));
        }

        return new self($tag);
    }

    /**
     * Returns the tagged aspects in the order the container lists them: the tag map first, then the definitions
     */
    public function collect(ConfigInterface $config): AspectList
    {
        $aspects = AspectList::fromClassNames([]);
        if ($this->tag === null) {
            return $aspects;
        }

        // Same order as the container: the tag map is applied before the definitions
        if ($config->has(self::TAGS_GROUP)) {
            $tagged = $config->get(self::TAGS_GROUP)[$this->tag] ?? [];
            $origin = sprintf('the "%s" tag of the "%s" config group', $this->tag, self::TAGS_GROUP);
            if (!is_array($tagged)) {
                throw new InvalidArgumentException(sprintf(
                    '%s must be a list of aspect class names, %s given.',
                    ucfirst($origin),
                    get_debug_type($tagged),
                ));
            }
            $aspects = $aspects->merge(AspectList::fromClassNames($tagged, $origin));
        }

        if ($config->has(self::DEFINITIONS_GROUP)) {
            $aspects = $aspects->merge(AspectList::fromClassNames(
                $this->taggedDefinitions($config->get(self::DEFINITIONS_GROUP)),
                sprintf('the "%s" tag of the "%s" config group', $this->tag, self::DEFINITIONS_GROUP),
            ));
        }

        return $aspects;
    }

    /**
     * Returns the ids of the definitions carrying the tag in their "tags" meta key
     *
     * @param array<mixed> $definitions
     *
     * @return list<mixed>
     */
    private function taggedDefinitions(array $definitions): array
    {
        $ids = [];
        foreach ($definitions as $id => $definition) {
            // Mirrors Yiisoft\Di\Helpers\DefinitionParser: a callable array (e.g. [Factory::class, 'create']) has no meta
            if (!is_array($definition) || (!isset($definition['definition']) && is_callable($definition, true))) {
                continue;
            }
            $tags = $definition['tags'] ?? [];
            if (is_array($tags) && in_array($this->tag, $tags, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
