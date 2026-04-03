<?php

/**
 * Central DB config accessor.
 *
 * Keeps one source of truth for classes/Database.php and init bootstrap.
 */
$env = require __DIR__ . '/env.php';

return $env['db'];
