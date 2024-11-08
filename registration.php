<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Opengento_MakegentoCli',
    __DIR__
);
