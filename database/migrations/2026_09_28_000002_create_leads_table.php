<?php

use App\Enums\LeadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email')->nullable()->index();
            $table->string('source', 50)->index();
            // DB-level enum (native ENUM on MySQL, CHECK constraint on SQLite)
            // backs up the application-level validation and Eloquent cast.
            $table->enum('status', LeadStatus::values())
                ->default(LeadStatus::New->value)
                ->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
