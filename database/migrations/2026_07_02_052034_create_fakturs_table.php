<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fakturs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nomor_faktur')->unique();
            $table->string('nama');
            $table->decimal('nominal', 14, 2);
            $table->json('items')->nullable();
            $table->string('terbilang');
            $table->text('memo')->nullable();
            $table->string('paper_size')->default('a4');
            $table->string('logo_path')->nullable();
            $table->string('pdf_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fakturs');
    }
};
