<?php

use Illuminate\Support\Facades\Http;

it('explains reader permissions without a provider key or budget use', function (string $message): void {
    config(['services.gemini.key' => null]);
    $scenario = deliveryScenario('reader');

    $response = $this->actingAs($scenario['user'])->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => $message]);

    $response->assertOk()->assertJsonPath('kind', 'help')->assertJsonPath('data', null)
        ->assertJsonPath('help.role', 'reader')->assertJsonPath('help.can_ingest', false)
        ->assertJsonPath('help.merchant_name', $scenario['merchant']->name)
        ->assertJsonCount(4, 'help.actions')->assertJsonCount(3, 'help.queries')
        ->assertJsonPath('help.queries.0.prompt', '¿Cuánto costaron las entregas la semana pasada?')
        ->assertJsonPath('help.queries.1.prompt', '¿Qué estados tienen las entregas del mensajero ID '.$scenario['courier']->id.' ('.$scenario['courier']->name.') la semana pasada?')
        ->assertJsonPath('help.queries.2.prompt', 'Busca entregas de Calle 80');
    expect(array_column($response->json('help.actions'), 'command'))->toBe(['/costos', '/estados', '/entregas', '/historial']);
    expect($response->json('message'))->toContain('No puedes ingresar eventos.');
    Http::assertNothingSent();
    $this->assertDatabaseCount('agent_calls', 0);
})->with(['/help', '¿Qué puedo hacer con mi rol de lector?', '¿Qué puedo hacer con mi rol de operador?', '¿Cuáles son mis permisos?']);

it('offers event entry only to an authorized operator', function (): void {
    config(['services.gemini.key' => null]);
    $scenario = deliveryScenario();

    $this->actingAs($scenario['user'])->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => '/evento'])
        ->assertOk()->assertJsonPath('help.role', 'operator')->assertJsonPath('help.can_ingest', true)
        ->assertJsonCount(1, 'help.actions')->assertJsonPath('help.actions.0.href', '/operations#event-entry');

    Http::assertNothingSent();
    $this->assertDatabaseCount('delivery_events', 0);
});

it('does not grant a reader event entry through commands', function (string $message): void {
    $scenario = deliveryScenario('reader');

    $response = $this->actingAs($scenario['user'])->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => $message]);

    $response->assertOk()->assertJsonPath('help.can_ingest', false)->assertJsonCount(4, 'help.actions');
    expect(array_column($response->json('help.actions'), 'target'))->not->toContain('event-entry');
    Http::assertNothingSent();
    $this->assertDatabaseCount('delivery_events', 0);
})->with(['/evento', '/reiniciar']);

it('resolves a navigation command to an allowed application link', function (string $command, string $target): void {
    $scenario = deliveryScenario('reader');

    $this->actingAs($scenario['user'])->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => $command])
        ->assertOk()->assertJsonCount(1, 'help.actions')->assertJsonPath('help.actions.0.href', '/operations#'.$target);

    Http::assertNothingSent();
})->with([
    ['/costos', 'costs-report'], ['/estados', 'courier-summary'], ['/entregas', 'delivery-search'], ['/historial', 'deliveries-table'],
]);

it('requires authentication and merchant access even for help', function (): void {
    $scenario = deliveryScenario('reader');
    $other = deliveryScenario();

    $this->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => '/help'])->assertUnauthorized();
    $this->actingAs($scenario['user'])->postJson('/api/v1/merchants/'.$other['merchant']->id.'/chat', ['message' => '/help'])->assertNotFound();

    Http::assertNothingSent();
    $this->assertDatabaseCount('agent_calls', 0);
});

it('keeps explicit help out of the business conversation history', function (): void {
    $scenario = deliveryScenario('reader');
    $history = [['role' => 'user', 'text' => 'Costos de ayer']];

    $this->actingAs($scenario['user'])->withSession(['chat' => ['merchant_id' => $scenario['merchant']->id, 'history' => $history]])
        ->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => '/help'])
        ->assertOk()->assertSessionHas('chat.history', $history);

    Http::assertNothingSent();
});

it('shows safe role-aware help when the model cannot select a tool', function (): void {
    config(['services.gemini.key' => 'test-only-key']);
    $scenario = deliveryScenario('reader');
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '<script>untrusted answer</script>']]]]]])]);

    $response = $this->actingAs($scenario['user'])->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => 'Pregunta incomprensible']);

    $response->assertOk()->assertJsonPath('kind', 'clarification')->assertJsonPath('data', null)
        ->assertJsonPath('help.can_ingest', false)->assertJsonCount(4, 'help.actions');
    expect($response->json('message'))->toContain('No pude resolver esa consulta.')->not->toContain('untrusted answer');
    Http::assertSentCount(1);
    $this->assertDatabaseHas('agent_calls', ['status' => 'clarification']);
});

it('does not suggest another merchants courier when none is assigned', function (): void {
    $scenario = deliveryScenario('reader');
    $scenario['merchant']->couriers()->detach();
    deliveryScenario();

    $this->actingAs($scenario['user'])->postJson('/api/v1/merchants/'.$scenario['merchant']->id.'/chat', ['message' => '/help'])
        ->assertOk()->assertJsonCount(3, 'help.queries')->assertJsonPath('help.queries.1.prompt', null);

    Http::assertNothingSent();
});
