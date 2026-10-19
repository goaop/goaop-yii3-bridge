<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\App\Service;

/**
 * Enum referenced by the configuration: reading the configuration loads it, no aspect advises it
 */
enum Tone: string
{
    case Loud = '!';
}
