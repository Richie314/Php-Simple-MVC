<?php

namespace Richie314\SimpleMvc\Demo;

use Richie314\SimpleMvc\Controllers\Attributes\RequireLogin;
use Richie314\SimpleMvc\Controllers\Controller;

class DemoController extends Controller
{
    public function index()
    {
        return $this->Render(
            view: 'Home/index',
            title: 'Home page'
        );
    }

    public function contact(?string $name = null, ?string $message = null)
    {
        return $this->Render(
            view: 'Home/contact',
            title: 'Send me a message',
        );
    }

    #[RequireLogin]
    public function secret()
    {
        return $this->Content(
            type: 'text/plain', 
            content: 'Congrats! You saw the secret content',
        );
    }
}