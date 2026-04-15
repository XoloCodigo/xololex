<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharepoint_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lawyer_id')->constrained();
            $table->foreignId('company_id')->constrained();
            $table->string('service_type');
            $table->decimal('hours_spent', 5, 2);
            $table->string('urgency_level');
            $table->boolean('requires_followup')->default(false);
            $table->date('followup_date')->nullable();
            $table->text('additional_notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('sharepoint_item_id')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('sharepoint_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharepoint_forms');
    }
};
