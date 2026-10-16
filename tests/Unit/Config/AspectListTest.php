<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Config;

use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ReportAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AspectListTest extends TestCase
{
    public function testAspectsAreDeduplicatedAndNormalized(): void
    {
        $aspects = AspectList::fromParams(['aspects' => [
            GreetingAspect::class,
            '\\' . GreetingAspect::class,
            ReportAspect::class,
        ]]);

        $this->assertSame([GreetingAspect::class, ReportAspect::class], $aspects->toArray());
        $this->assertCount(2, $aspects);
        $this->assertSame([GreetingAspect::class, ReportAspect::class], iterator_to_array($aspects));
    }

    public function testMissingAspectsParameterMeansNoAspects(): void
    {
        $this->assertCount(0, AspectList::fromParams([]));
    }

    public function testClassesAreNotAutoloaded(): void
    {
        $aspects = AspectList::fromClassNames(['App\Aspect\NotLoadedYet']);

        $this->assertSame(['App\Aspect\NotLoadedYet'], $aspects->toArray());
        $this->assertFalse(class_exists('App\Aspect\NotLoadedYet', false));
    }

    public function testAspectsParameterMustBeAList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "goaop/goaop-yii3-bridge" parameter "aspects" must be a list of aspect class names, string given.');

        AspectList::fromParams(['aspects' => GreetingAspect::class]);
    }

    public function testInvalidClassNameIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('parameter "aspects" must contain aspect class names, "App\\My Aspect" given.');

        AspectList::fromClassNames(['App\\My Aspect']);
    }

    public function testNonStringIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('parameter "aspects" must contain aspect class names, int given.');

        AspectList::fromClassNames([42]);
    }

    public function testMergeAppendsTheAspectsNotListedYet(): void
    {
        $merged = AspectList::fromClassNames([GreetingAspect::class])
            ->merge(AspectList::fromClassNames([ReportAspect::class, GreetingAspect::class], 'the tag'));

        $this->assertSame([GreetingAspect::class, ReportAspect::class], $merged->toArray());
    }

    public function testAssertAspectsNamesWhereTheClassIsDeclared(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "' . Journal::class . '" listed in the "goaop.aspect" tag of the "di" config group must implement');

        AspectList::fromClassNames([GreetingAspect::class])
            ->merge(AspectList::fromClassNames([Journal::class], 'the "goaop.aspect" tag of the "di" config group'))
            ->assertAspects();
    }

    public function testAssertAspectsAcceptsAspects(): void
    {
        AspectList::fromClassNames([GreetingAspect::class, ReportAspect::class])->assertAspects();

        $this->addToAssertionCount(1);
    }

    public function testAssertAspectsReportsMissingClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Aspect class "App\Aspect\Missing" listed in the "goaop/goaop-yii3-bridge" parameter "aspects" does not exist.');

        AspectList::fromClassNames(['App\Aspect\Missing'])->assertAspects();
    }

    public function testAssertAspectsReportsClassNotImplementingAspect(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "' . Journal::class . '" listed in the "goaop/goaop-yii3-bridge" parameter "aspects" must implement the "Go\Aop\Aspect" interface.');

        AspectList::fromClassNames([Journal::class])->assertAspects();
    }
}
