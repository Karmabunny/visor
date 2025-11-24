<?php

use karmabunny\router\Route;
use karmabunny\visor\router\Controller;

class TestController extends Controller
{
    #[Route('GET /test')]
    public function index()
    {
        return 'Hello, world!';
    }
}
