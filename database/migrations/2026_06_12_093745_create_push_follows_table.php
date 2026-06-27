<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A push subscriber's follow snapshot — one row per followed team or
 * competition, replaced wholesale on every sync from the browser. A pivot
 * table (not JSON) so the audience query for a goal is an indexed lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('followed_id', 32);

            $table->unique(['push_subscriber_id', 'type', 'followed_id'], 'push_follows_unique');
            $table->index(['type', 'followed_id'], 'push_follows_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_follows');
    }
};
