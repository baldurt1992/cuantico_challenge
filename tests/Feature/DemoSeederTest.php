<?php

use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\User;
use Database\Seeders\DemoSeeder;

it('initializes the demonstration only once and preserves changed credentials', function (): void {
    $this->seed(DemoSeeder::class);
    $reader = User::where('email', 'lector@cuantico.test')->firstOrFail();
    $reader->update(['password' => 'changed-password']);
    $hash = $reader->fresh()->password;
    $eventCount = DeliveryEvent::count();

    $this->seed(DemoSeeder::class);

    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('merchants', 3);
    $this->assertDatabaseCount('delivery_events', $eventCount);
    expect($reader->fresh()->password)->toBe($hash);
    expect(Delivery::where('quality', 'conflict')->count())->toBe(3);
    expect(Delivery::whereNotNull('cost_minor')->count())->toBe(9);
});
