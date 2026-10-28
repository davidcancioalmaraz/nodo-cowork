<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_miembro_and_recepcion_can_authenticate_with_any_password(): void
    {
        foreach (['miembro', 'recepcion'] as $usuario) {
            $response = $this->post('/login', [
                'usuario' => $usuario,
                'password' => 'cualquier-cosa',
            ]);

            $this->assertAuthenticated();
            $response->assertRedirect(route('dashboard', absolute: false));

            $this->post('/logout');
        }

        $this->assertDatabaseHas('users', ['email' => 'miembro@nodo.test']);
    }

    public function test_unknown_users_can_not_authenticate(): void
    {
        $this->post('/login', [
            'usuario' => 'desconocido',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }
}
