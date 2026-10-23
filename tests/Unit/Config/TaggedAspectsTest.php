<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Config;

use Go\Yii3\GoAopBridge\Config\TaggedAspects;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\PackageAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ReportAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Config\ConfigInterface;

final class TaggedAspectsTest extends TestCase
{
    public function testTaggedServicesAreCollectedInTheOrderOfTheContainer(): void
    {
        $config = self::config([
            'di' => [
                GreetingAspect::class => ['tags' => ['goaop.aspect']],
                ReportAspect::class   => ['__construct()' => ['label' => 'audited'], 'tags' => ['logging', 'goaop.aspect']],
                PackageAspect::class  => ['definition' => static fn(): object => new \stdClass(), 'tags' => ['goaop.aspect']],
                Journal::class        => ['tags' => ['logging']],
                'factory'             => [Journal::class, 'tags'],
                'closure'             => static fn(): object => new \stdClass(),
            ],
            'di-tags' => [
                'goaop.aspect' => [ConfiguredServiceAspect::class, GreetingAspect::class],
                'logging'      => [Journal::class],
            ],
        ]);

        $aspects = (new TaggedAspects('goaop.aspect'))->collect($config);

        $this->assertSame(
            [ConfiguredServiceAspect::class, GreetingAspect::class, ReportAspect::class, PackageAspect::class],
            $aspects->toArray(),
        );
    }

    public function testMissingGroupsMeanNoTaggedAspects(): void
    {
        $this->assertCount(0, (new TaggedAspects('goaop.aspect'))->collect(self::config([])));
    }

    public function testCustomTagIsCollected(): void
    {
        $config = self::config(['di' => [GreetingAspect::class => ['tags' => ['app.aspect']]]]);

        $aspects = TaggedAspects::fromParams(['aspectTag' => 'app.aspect'])->collect($config);

        $this->assertSame([GreetingAspect::class], $aspects->toArray());
    }

    public function testNullTagDisablesTheCollection(): void
    {
        $config = self::config(['di' => [GreetingAspect::class => ['tags' => ['goaop.aspect']]]]);

        $this->assertCount(0, TaggedAspects::fromParams(['aspectTag' => null])->collect($config));
    }

    public function testCollectionIsOffWithoutParameter(): void
    {
        $config = self::config(['di' => [GreetingAspect::class => ['tags' => ['goaop.aspect']]]]);

        $this->assertCount(0, TaggedAspects::fromParams([])->collect($config));
    }

    public function testInvalidTagParameterIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "goaop/goaop-yii3-bridge" parameter "aspectTag" must be a non-empty tag name or null, int given.');

        TaggedAspects::fromParams(['aspectTag' => 42]);
    }

    public function testTaggedServiceWithoutClassNameIdIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "goaop.aspect" tag of the "di" config group must contain aspect class names, "audit.aspect" given.');

        (new TaggedAspects('goaop.aspect'))->collect(self::config(['di' => ['audit.aspect' => ['tags' => ['goaop.aspect']]]]));
    }

    public function testTagMapThatIsNotAListIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "goaop.aspect" tag of the "di-tags" config group must be a list of aspect class names, string given.');

        (new TaggedAspects('goaop.aspect'))->collect(self::config(['di-tags' => ['goaop.aspect' => GreetingAspect::class]]));
    }

    /**
     * @param array<string, array<mixed>> $groups
     */
    private static function config(array $groups): ConfigInterface
    {
        return new readonly class ($groups) implements ConfigInterface {
            /**
             * @param array<string, array<mixed>> $groups
             */
            public function __construct(private array $groups) {}

            /**
             * @return array<mixed>
             */
            public function get(string $group): array
            {
                return $this->groups[$group];
            }

            public function has(string $group): bool
            {
                return isset($this->groups[$group]);
            }
        };
    }
}
