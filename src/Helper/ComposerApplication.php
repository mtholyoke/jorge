<?php

namespace MountHolyoke\Jorge\Helper;

use Composer\Composer;
use Composer\Console\Application;

class ComposerApplication extends Application
{
    public function setComposer(Composer $composer)
    {
        $this->composer = $composer;
    }
}
