<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobIdRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandy_and_john_see_jobs_in_the_configured_id_range_and_applications_reach_sandy(): void
    {
        config([
            'job_listing.id_ranges' => [
                'sandy@gmail.com' => [100, 2200],
                'john@gmail.com' => [100, 2200],
            ],
            'recruiter_sharing.shared_emails' => ['hire@gmail.com', 'morgan@gmail.com'],
        ]);

        $sandy = User::factory()->create(['email' => 'sandy@gmail.com', 'role' => 'hr']);
        $john = User::factory()->create(['email' => 'john@gmail.com', 'role' => 'job_seeker']);
        $otherRecruiter = User::factory()->create(['role' => 'hr']);
        $alex = User::factory()->create(['email' => 'hire@gmail.com', 'role' => 'hr']);
        $morgan = User::factory()->create(['email' => 'morgan@gmail.com', 'role' => 'hr']);

        $this->createJob(99, $sandy);
        $sandyJob = $this->createJob(100, $sandy);
        $this->createJob(150, $otherRecruiter);
        $this->createJob(160, $alex);
        $this->createJob(170, $morgan);
        $this->createJob(2200, $sandy);
        $this->createJob(2201, $sandy);

        $publicJobs = $this->actingAs($john, 'sanctum')
            ->getJson('/api/public/jobs?per_page=2200')
            ->assertOk()
            ->json('data');
        $publicJobIds = collect($publicJobs)->pluck('id')->sort()->values()->all();
        $this->assertSame([100, 150, 160, 170, 2200], $publicJobIds);

        $recruiterJobIds = $this->actingAs($sandy)->getJson('/api/jobs')
            ->assertOk()
            ->json('jobs');
        $this->assertSame([100, 2200], collect($recruiterJobIds)->pluck('id')->sort()->values()->all());
        $this->actingAs($alex)->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(2, 'jobs');

        $recommendationIds = $this->actingAs($john)->getJson('/api/user/recommendations')
            ->assertOk()
            ->json('recommendations');
        $this->assertSame([100, 150, 160, 170, 2200], collect($recommendationIds)->pluck('job.id')->sort()->values()->all());

        Candidate::create([
            'user_id' => $john->id,
            'resume_path' => 'resumes/john.pdf',
            'resume_text' => 'PHP and Laravel software engineer.',
            'extracted_skills_json' => ['PHP'],
            'status' => 'done',
        ]);
        $application = $this->actingAs($john)->postJson("/api/user/jobs/{$sandyJob->id}/apply")
            ->assertCreated()
            ->json('application');

        $this->actingAs($sandy)->getJson('/api/applications')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.job.id', $sandyJob->id);
        $this->actingAs($otherRecruiter)->getJson('/api/applications')
            ->assertOk()
            ->assertJsonCount(0, 'applications');
    }

    private function createJob(int $id, User $recruiter): Job
    {
        DB::table('jobs')->insert([
            'id' => $id,
            'user_id' => $recruiter->id,
            'title' => "Job {$id}",
            'description' => 'A role for testing job ID filters.',
            'department' => 'Software Engineering',
            'employment_type' => 'Full-time',
            'required_skills_json' => json_encode(['PHP']),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Job::findOrFail($id);
    }
}
