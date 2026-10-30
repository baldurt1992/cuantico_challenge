<?php

use App\Models\User;

it('authenticates valid local credentials and invalidates them at logout', function (): void {
    $user = User::factory()->create(['password' => 'test-password']);

    $this->post('/login', ['email' => $user->email, 'password' => 'test-password'])->assertRedirect('/operations');
    $this->assertAuthenticatedAs($user);
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('rejects wrong credentials and limits repeated attempts', function (): void {
    $user = User::factory()->create();
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable()
        ->assertJsonValidationErrors('email')->assertSee('Demasiados intentos');
    $this->assertGuest();
});

it('requires authentication for operations endpoints', function (): void {
    $this->getJson('/api/v1/merchants/1/operations?period=last_week')->assertUnauthorized();
});
