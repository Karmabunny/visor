<?php

use karmabunny\visor\router\Controller;

class TestController extends Controller
{
    /**
     * @route GET /test
     */
    public function index()
    {
        return 'Hello, world!';
    }
}
