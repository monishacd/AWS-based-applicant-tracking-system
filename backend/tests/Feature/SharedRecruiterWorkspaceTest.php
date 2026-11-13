<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedRecruiterWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_recruiters_share_jobs_and_applications_without_exposing_them_to_other_recruiters(): void
    {
        config(['recruiter_sharing.shared_emails' => ['hire@gmail.com', 'morgan@gmail.com']]);

        $alex = User::factory()->create(['email' => 'hire@gmail.com', 'role' => 'hr']);
        $morgan = User::factory()->create(['email' => 'morgan@gmail.com', 'role' => 'hr']);
        $otherRecruiter = User::factory()->create(['role' => 'hr']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);

        $morganJob = $this->createJob($morgan, 'Morgan role');
        $this->createJob($alex, 'Alex role');
        $this->createJob($otherRecruiter, 'Private role');

        $candidate = Candidate::create([
            'user_id' => $seeker->id,
            'resume_path' => 'resumes/shared-workspace.pdf',
            'resume_text' => 'PHP and Laravel software engineer.',
            'extracted_skills_json' => ['PHP'],
            'status' => 'done',
        ]);
        $application = $this->actingAs($seeker)
            ->postJson("/api/user/jobs/{$morganJob->id}/apply")
            ->assertCreated()
            ->json('application');

        $this->assertSame($seeker->id, $application['user_id']);
        $this->assertSame($candidate->id, $application['candidate_id']);

        $this->actingAs($alex)->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(2, 'jobs');
        $this->actingAs($morgan)->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(2, 'jobs');

        $this->actingAs($alex)->getJson('/api/applications')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.job.id', $morganJob->id);
        $this->actingAs($alex)->getJson('/api/candidates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $candidate->id);
        $this->actingAs($alex)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('stats.total_jobs', 2)
            ->assertJsonPath('stats.applications', 1);
        $this->actingAs($alex)->patchJson("/api/applications/{$application['id']}", [
            'status' => 'shortlisted',
        ])->assertOk()
            ->assertJsonPath('application.status', 'shortlisted');

        $this->actingAs($otherRecruiter)->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.title', 'Private role');
        $this->actingAs($otherRecruiter)->getJson('/api/applications')
            ->assertOk()
            ->assertJsonCount(0, 'applications');
        $this->actingAs($otherRecruiter)->patchJson("/api/applications/{$application['id']}", [
            'status' => 'rejected',
        ])->assertForbidden();
    }

    private function createJob(User $recruiter, string $title): Job
    {
        return Job::create([
            'user_id' => $recruiter->id,
            'title' => $title,
            'description' => 'A role for testing shared recruiter workspaces.',
            'department' => 'Software Engineering',
            'employment_type' => 'Full-time',
            'required_skills_json' => ['PHP'],
            'status' => 'open',
        ]);
    }
}
