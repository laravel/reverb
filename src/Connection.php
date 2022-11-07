<?php

namespace Reverb;

use Closure;

class Connection
{
    protected $id;

    public function __construct(protected string $identifier, protected Closure $sendUsing)
    {
    }

    public function identifier()
    {
        return $this->identifier;
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
