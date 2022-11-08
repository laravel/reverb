<?php

namespace Reverb;

use Closure;
use Throwable;

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
        try {
            ($this->sendUsing)($message);
        } catch (Throwable $e) {
            echo 'Unable to send message to connection: '.$e->getMessage();
        }
    }

    protected function generateId()
    {
        return sprintf('%d.%d', random_int(1, 1000000000), random_int(1, 1000000000));
    }
}
