<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per anonymous browser that enabled push (no user accounts — see
 * docs/PUSH_NOTIFICATIONS.md). The package's push_subscriptions row morphs to
 * this model; the follow snapshot lives in push_follows. `updated_at` is
 * touched on every sync and drives pruning of abandoned subscribers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscribers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscribers');
    }
};
