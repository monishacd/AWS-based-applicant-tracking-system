<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('type')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('location')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('outcome')->nullable();
            $table->timestamp('outcome_published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        if (Schema::hasColumn('applications', 'interview_at')) {
            $legacyInterviews = DB::table('applications')
                ->whereNotNull('interview_at')
                ->where('status', 'interview')
                ->get(['id', 'interview_at', 'interview_notes', 'created_at', 'updated_at']);

            foreach ($legacyInterviews as $legacyInterview) {
                DB::table('interviews')->insert([
                    'application_id' => $legacyInterview->id,
                    'scheduled_at' => $legacyInterview->interview_at,
                    'instructions' => $legacyInterview->interview_notes,
                    'status' => 'scheduled',
                    'created_at' => $legacyInterview->created_at,
                    'updated_at' => $legacyInterview->updated_at,
                ]);
            }
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('interviews');
    }
};
