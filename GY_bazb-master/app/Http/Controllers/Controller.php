<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Traits\Actual;
use App\Traits\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        echo header("Access-Control-Allow-Origin:*");
        $this->reflectionBoot();
    }

    protected function reflectionBoot()
    {
        if (method_exists($this, 'boot')) {
            $reflect = new \ReflectionMethod($this, 'boot');
            $p2 = getReflectionParamValue($reflect);
            $this->boot(...$p2);
        }
    }
}
