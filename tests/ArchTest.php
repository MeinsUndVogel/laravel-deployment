<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security()->ignoring(['exec', 'shell_exec']);

arch('strict mode')
    ->expect('src')
    ->toUseStrictEquality()
    ->toUseStrictTypes()
    ->classes()->toBeFinal();
