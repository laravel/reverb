<?php

use Laravel\Reverb\Contracts\ConnectionManager;

/**
 * Return the connection manager.
 */
function connections(): ConnectionManager
{
    return app(ConnectionManager::class);
}
