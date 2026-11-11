<?php

namespace Richie314\SimpleMvc\Demo;

use Richie314\SimpleMvc\Controllers\Attributes\RequireLogin;
use Richie314\SimpleMvc\Controllers\Controller;

class DemoController extends Controller
{
    /**
     * Render the demo home page.
     *
     * @return \Richie314\SimpleMvc\Http\StatusCode Response status returned by the controller helper.
     */
    public function index()
    {
        return $this->Render(
            view: 'Home/index',
            title: 'Home page'
        );
    }

    /**
     * Render the demo contact form.
     *
     * @param string|null $name Optional submitted name; currently unused.
     * @param string|null $message Optional submitted message; currently unused.
     * @return \Richie314\SimpleMvc\Http\StatusCode Response status returned by the controller helper.
     */
    public function contact(?string $name = null, ?string $message = null)
    {
        return $this->Render(
            view: 'Home/contact',
            title: 'Send me a message',
        );
    }

    #[RequireLogin]
    /**
     * Send the protected demo content after the login attribute succeeds.
     *
     * @return \Richie314\SimpleMvc\Http\StatusCode Response status returned by the controller helper.
     */
    public function secret()
    {
        return $this->Content(
            type: 'text/plain', 
            content: 'Congrats! You saw the secret content',
        );
    }
}