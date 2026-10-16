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

namespace Go\Yii3\GoAopBridge\Kernel;

use LogicException;
use Psr\Container\ContainerInterface;

/**
 * Resolves aspects from the Yii container that is attached after the aspect kernel was initialized
 *
 * The kernel is initialized from the entry script, before the Yii container exists: it registers every aspect as a
 * deferred service whose factory asks this resolver. The bootstrap callback of the bridge attaches the container
 * later, long before an advice runs and the factory of its aspect is invoked.
 */
final class ContainerResolver
{
    private ?ContainerInterface $container = null;

    public function attach(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function isAttached(): bool
    {
        return $this->container !== null;
    }

    /**
     * Returns the service with the given id from the attached container
     *
     * @throws LogicException When no container is attached yet
     */
    public function get(string $id): mixed
    {
        if ($this->container === null) {
            throw new LogicException(sprintf(
                'Service "%s" is requested before the Yii container is attached to the aspect kernel. An advised '
                . 'method was called before the "bootstrap" config group of goaop/goaop-yii3-bridge has run, '
                . 'e.g. while the container is built.',
                $id,
            ));
        }

        return $this->container->get($id);
    }
}
