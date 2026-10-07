<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_public_login_entries_use_the_current_portal_login_url()
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
        $this->get('/customer/login')->assertRedirect(route('login'));
        $this->get('https://zx.freitora.online/customer/login')
            ->assertRedirect('https://zx.freitora.online/login');
        $this->get('https://another.freitora.online/customer/login')
            ->assertRedirect('https://another.freitora.online/login');
    }
}
