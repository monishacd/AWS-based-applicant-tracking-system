<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_recruiter_can_filter_candidates_by_applied_role_and_application_status(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $selectedJob = $this->createJob($recruiter, 'Technical Writer - FinTech');
        $otherJob = $this->createJob($recruiter, 'Technical Writer - HealthTech');
        $unusedJob = $this->createJob($recruiter, 'Software Engineer');
        $candidate = Candidate::create([
            'user_id' => $seeker->id,
            'resume_path' => 'resumes/candidate.pdf',
            'status' => 'done',
            'parsed_name' => 'Test Candidate',
        ]);

        Application::create([
            'user_id' => $seeker->id,
            'candidate_id' => $candidate->id,
            'job_id' => $selectedJob->id,
            'status' => 'cleared',
        ]);
        Application::create([
            'user_id' => $seeker->id,
            'candidate_id' => $candidate->id,
            'job_id' => $otherJob->id,
            'status' => 'interview',
        ]);

        $jobsResponse = $this->actingAs($recruiter)->getJson('/api/jobs')->assertOk();
        $jobs = collect($jobsResponse->json('jobs'))->keyBy('id');
        $this->assertSame(1, $jobs[$selectedJob->id]['applications_count']);
        $this->assertSame(1, $jobs[$otherJob->id]['applications_count']);
        $this->assertSame(0, $jobs[$unusedJob->id]['applications_count']);

        $this->getJson("/api/candidates?job_id={$selectedJob->id}&status=cleared")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $candidate->id)
            ->assertJsonPath('data.0.applications.0.job.title', $selectedJob->title)
            ->assertJsonPath('data.0.applications.0.status', 'cleared');

        $this->getJson('/api/candidates?status=in_review')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $candidate->id);
    }

    private function createJob(User $recruiter, string $title): Job
    {
        return Job::create([
            'user_id' => $recruiter->id,
            'title' => $title,
            'description' => 'Test role.',
            'status' => 'open',
        ]);
    }
}
