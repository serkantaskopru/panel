<?php

namespace App\Events\Admin\Tools\Cache;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class View
{
    use Dispatchable, SerializesModels;

    public $cache;

    public function __construct()
    {
        $this->cache;
    }
}
