<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_email');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        DB::statement(
            'CREATE UNIQUE INDEX bookings_one_active_per_slot ON bookings (slot_id) WHERE cancelled_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
