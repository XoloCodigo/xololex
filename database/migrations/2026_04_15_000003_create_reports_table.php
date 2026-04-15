<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('lawyer_id')->constrained();
            $table->foreignId('company_id')->constrained();
            $table->string('visit_reason');
            $table->string('contact_met')->nullable();
            $table->string('contact_position')->nullable();
            $table->text('findings');
            $table->text('risks')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('observations')->nullable();
            $table->date('visit_date');
            $table->string('status')->default('draft');
            $table->string('word_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('sharepoint_url')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
