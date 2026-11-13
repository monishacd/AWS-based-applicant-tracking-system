<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('applications', 'shortlisted_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->timestamp('shortlisted_at')->nullable()->after('status');
            });
        }

        $applications = DB::table('applications')
            ->whereIn('status', ['shortlisted', 'interview', 'cleared'])
            ->orWhereNotNull('interview_at');

        if (Schema::hasColumn('applications', 'offer_letter_text')) {
            $applications->orWhereNotNull('offer_letter_text');
        }

        $applications->update(['shortlisted_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('applications', 'shortlisted_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('shortlisted_at');
            });
        }
    }
};
