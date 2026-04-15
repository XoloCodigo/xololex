<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lawyer_id')->constrained();
            $table->string('phone');
            $table->string('flow')->default('idle');
            $table->string('step')->default('start');
            $table->json('data')->nullable();
            $table->foreignId('report_id')->nullable()->constrained();
            $table->foreignId('sharepoint_form_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
