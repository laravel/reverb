<?php

namespace Laravel\Reverb\Concerns;

use Laravel\Reverb\Application;

trait InteractsWithApplications
{
    /**
     * The application the channel manager should be scoped to.
     *
     * @param  \Laravel\Reverb\Application  $application
     * @return self
     */
    public function for(Application $application): self
    {
        $this->application = $application;

        return $this;
    }
}
