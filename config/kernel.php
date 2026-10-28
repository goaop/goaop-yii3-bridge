<?php

declare(strict_types=1);

/*
 * Parameters AopBootstrap::init() starts the aspect kernel with.
 *
 * yiisoft/config evaluates the files of every group with the parameters of the Config they are read from. Read from
 * the config of the runner, this group sees exactly the parameters of the runner ("params-web" or "params-console",
 * with "params" nested), whatever the runner is.
 */

/** @var array<mixed> $params */

return [
    'goaop/goaop-yii3-bridge' => $params['goaop/goaop-yii3-bridge'] ?? null,
    'yiisoft/aliases' => $params['yiisoft/aliases'] ?? null,
];
