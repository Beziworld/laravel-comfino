<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\Comfino')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\Comfino\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\Comfino\Exceptions')
    ->toExtend('Beziworld\Comfino\Exceptions\ComfinoException')
    ->ignoring('Beziworld\Comfino\Exceptions\ComfinoException');
