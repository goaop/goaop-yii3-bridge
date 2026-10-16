<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Kernel;

use Go\Yii3\GoAopBridge\Kernel\ContainerResolver;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;
use LogicException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

final class ContainerResolverTest extends TestCase
{
    public function testServicesAreResolvedFromAttachedContainer(): void
    {
        $journal   = new Journal();
        $container = new Container(ContainerConfig::create()->withDefinitions([Journal::class => $journal]));
        $resolver  = new ContainerResolver();

        $this->assertFalse($resolver->isAttached());
        $resolver->attach($container);

        $this->assertTrue($resolver->isAttached());
        $this->assertSame($journal, $resolver->get(Journal::class));
    }

    public function testResolvingBeforeAttachIsReported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service "' . Journal::class . '" is requested before the Yii container is attached to the aspect kernel.');

        (new ContainerResolver())->get(Journal::class);
    }
}
