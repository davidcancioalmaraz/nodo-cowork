<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_redirects_guests_to_the_login(): void
    {
        $this->get('/')->assertRedirect('/disponibilidad');
        $this->get('/disponibilidad')->assertRedirect('/login');
    }

    public function test_the_root_takes_authenticated_users_to_disponibilidad(): void
    {
        $this->post('/login', ['usuario' => 'miembro', 'password' => 'cualquiera']);

        $this->get('/')->assertRedirect('/disponibilidad');
        $this->get('/disponibilidad')->assertOk();
    }
}
