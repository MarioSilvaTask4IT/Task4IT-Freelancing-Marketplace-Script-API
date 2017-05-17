<?php

namespace Task4ItAPI\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Dingo\Api\Routing\Helpers;

abstract class Controller extends BaseController
{
    use DispatchesJobs, ValidatesRequests, Helpers;

    /**
     * Setup the layout used by the controller.
     *
     * @return void
     */
    protected function setupLayout()
    {
        if ( ! is_null($this->layout)) {
            $this->layout = View::make($this->layout);
        }
    }

    protected function translateErrors($errors)
    {
        $errors = array_map(
            function ($error) {
                return trans($error);
            },
            $errors
        );

        return $errors;
    }
}
