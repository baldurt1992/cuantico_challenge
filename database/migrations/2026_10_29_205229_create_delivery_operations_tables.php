<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('reader');
        });
        Schema::create('merchants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('notes')->default('');
            $table->string('timezone')->default('America/Bogota');
            $table->timestamps();
        });
        Schema::create('merchant_user', function (Blueprint $table): void {
            $table->foreignId('merchant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->primary(['merchant_id', 'user_id']);
        });
        Schema::create('couriers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('courier_merchant', function (Blueprint $table): void {
            $table->foreignId('courier_id')->constrained();
            $table->foreignId('merchant_id')->constrained();
            $table->primary(['courier_id', 'merchant_id']);
        });
        Schema::create('tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained();
            $table->string('effective_from', 26);
            $table->unsignedBigInteger('base_minor');
            $table->unsignedBigInteger('per_km_minor');
            $table->string('currency', 3)->default('COP');
            $table->string('timezone');
            $table->timestamps();
            $table->unique(['merchant_id', 'effective_from']);
        });
        Schema::create('deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('merchant_id')->constrained();
            $table->foreignId('courier_id')->nullable()->constrained();
            $table->string('status')->nullable();
            $table->string('quality')->default('pending');
            $table->json('issues')->nullable();
            $table->string('delivery_address')->nullable();
            $table->json('planned_pickup')->nullable();
            $table->json('planned_destination')->nullable();
            $table->string('created_occurred_at', 26)->nullable();
            $table->string('completed_at', 26)->nullable();
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->json('calculation')->nullable();
            $table->timestamps();
            $table->index(['merchant_id', 'completed_at']);
            $table->index(['merchant_id', 'courier_id', 'created_occurred_at']);
        });
        Schema::create('delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('emitter_id')->constrained('users');
            $table->uuid('event_id');
            $table->foreignUuid('delivery_id')->constrained();
            $table->string('type');
            $table->string('occurred_at', 26);
            $table->string('received_at', 26);
            $table->json('data');
            $table->string('fingerprint', 64);
            $table->unique(['emitter_id', 'event_id']);
            $table->index(['delivery_id', 'occurred_at']);
        });
        Schema::create('delivery_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('delivery_id')->constrained();
            $table->foreignId('event_id')->constrained('delivery_events');
            $table->json('snapshot');
            $table->string('reason');
            $table->timestamps();
        });
        Schema::create('demo_initializations', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->timestamp('created_at');
        });
        Schema::create('agent_calls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('merchant_id')->constrained();
            $table->string('status');
            $table->string('tool')->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        foreach (['demo_initializations', 'agent_calls', 'delivery_revisions', 'delivery_events', 'deliveries', 'tariffs', 'courier_merchant', 'couriers', 'merchant_user', 'merchants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
