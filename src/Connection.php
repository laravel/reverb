<?php

namespace Reverb;

use Closure;

class Connection
{
    protected $id;

    public function __construct(protected Closure $sendUsing)
    {
    }

    public function id()
    {
        if (! $this->id) {
            $this->id = $this->generateId();
        }

        return $this->id;
    }

    public function send($message)
    {
        ($this->sendUsing)($message);
    }

    protected function generateId()
    {
        return sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));
    }
}
