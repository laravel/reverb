<?php

namespace Reverb\Contracts;

interface ChannelManager
{
    public function add($connection);

    public function remove($connection);

    public function all();
}
