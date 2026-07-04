<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('notes');
            $table->date('note_date');
            $table->timestamps();

            $table->index(['user_id', 'note_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
