<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use App\Notifications\InterviewUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_recruiter_can_schedule_reschedule_publish_outcome_and_cancel_an_interview(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $job = $this->actingAs($recruiter)->postJson('/api/jobs', [
            'title' => 'Product Designer',
            'description' => 'Design product experiences.',
            'department' => 'UI/UX Design',
            'employment_type' => 'Full-time',
            'required_skills' => ['Figma'],
        ])->assertCreated()->json('job');
        Candidate::create([
            'user_id' => $seeker->id,
            'resume_path' => "resumes/{$seeker->id}.pdf",
            'resume_text' => 'Product designer with Figma experience.',
            'extracted_skills_json' => ['Figma'],
            'status' => 'done',
            'parsed_name' => 'Morgan Candidate',
            'parsed_email' => $seeker->email,
        ]);
        $application = $this->actingAs($seeker)
            ->postJson("/api/user/jobs/{$job['id']}/apply")
            ->assertCreated()
            ->json('application');
        $application = Application::findOrFail($application['id']);
        $scheduledAt = now()->addDays(2)->startOfHour();

        $created = $this->actingAs($recruiter)->postJson('/api/interviews', [
            'application_id' => $application->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'type' => 'online',
            'meeting_url' => 'https://meet.example.test/interview',
            'instructions' => 'Bring a portfolio example.',
        ])->assertCreated()
            ->assertJsonPath('interview.application_id', $application->id)
            ->assertJsonPath('interview.candidate.name', 'Morgan Candidate')
            ->assertJsonPath('interview.job.title', 'Product Designer')
            ->assertJsonPath('interview.status', 'scheduled');

        $interviewId = $created->json('interview.id');
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $seeker->id,
            'type' => InterviewUpdateNotification::class,
        ]);
        $this->actingAs($recruiter)->getJson('/api/interviews')
            ->assertOk()
            ->assertJsonCount(1, 'interviews');
        $this->actingAs($seeker)->getJson('/api/user/interviews')
            ->assertOk()
            ->assertJsonPath('interviews.0.meeting_url', 'https://meet.example.test/interview')
            ->assertJsonPath('interviews.0.outcome', null)
            ->assertJsonPath('updates.0.data.event', 'scheduled');

        $rescheduledAt = now()->addDays(5)->setTime(14, 30);
        $this->actingAs($recruiter)->putJson("/api/interviews/{$interviewId}", [
            'scheduled_at' => $rescheduledAt->toIso8601String(),
            'type' => 'in_person',
            'location' => 'Main office, room 4',
            'meeting_url' => null,
            'instructions' => 'Check in at reception.',
        ])->assertOk()
            ->assertJsonPath('interview.status', 'rescheduled')
            ->assertJsonPath('interview.location', 'Main office, room 4');

        $this->actingAs($recruiter)->patchJson("/api/interviews/{$interviewId}/outcome", [
            'outcome' => 'next_round',
            'published' => false,
        ])->assertOk()
            ->assertJsonPath('interview.outcome', 'next_round')
            ->assertJsonPath('interview.outcome_published_at', null);
        $this->actingAs($seeker)->getJson('/api/user/interviews')
            ->assertOk()
            ->assertJsonPath('interviews.0.outcome', null);

        $this->actingAs($recruiter)->patchJson("/api/interviews/{$interviewId}/outcome", [
            'outcome' => 'selected',
            'published' => true,
        ])->assertOk()
            ->assertJsonPath('interview.outcome', 'selected');
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'cleared']);
        $this->actingAs($seeker)->getJson('/api/user/interviews')
            ->assertOk()
            ->assertJsonPath('interviews.0.outcome', 'selected')
            ->assertJsonPath('interviews.0.location', 'Main office, room 4');

        $this->actingAs($recruiter)->postJson("/api/interviews/{$interviewId}/cancel")
            ->assertOk()
            ->assertJsonPath('interview.status', 'cancelled');
        $this->actingAs($seeker)->getJson('/api/user/interviews')
            ->assertOk()
            ->assertJsonPath('interviews.0.status', 'cancelled')
            ->assertJsonFragment(['event' => 'cancelled']);
    }

    public function test_recruiter_and_job_seeker_interview_access_is_scoped_to_relationship_ownership(): void
    {
        $alex = User::factory()->create(['role' => 'hr']);
        $david = User::factory()->create(['role' => 'hr']);
        $morgan = User::factory()->create(['role' => 'job_seeker']);
        $otherSeeker = User::factory()->create(['role' => 'job_seeker']);
        $alexApplication = $this->createAppliedApplication($alex, $morgan, 'Identical Role');
        $davidApplication = $this->createAppliedApplication($david, $otherSeeker, 'Identical Role');
        $interview = Interview::create([
            'application_id' => $alexApplication->id,
            'scheduled_at' => now()->addDays(2),
            'type' => 'online',
            'meeting_url' => 'https://meet.example.test/alex',
            'status' => 'scheduled',
        ]);
        Interview::create([
            'application_id' => $davidApplication->id,
            'scheduled_at' => now()->addDays(3),
            'type' => 'online',
            'meeting_url' => 'https://meet.example.test/david',
            'status' => 'scheduled',
        ]);

        $this->actingAs($alex)->getJson('/api/interviews')
            ->assertOk()
            ->assertJsonCount(1, 'interviews')
            ->assertJsonPath('interviews.0.job.id', $alexApplication->job_id);
        $this->actingAs($david)->getJson('/api/interviews')
            ->assertOk()
            ->assertJsonCount(1, 'interviews')
            ->assertJsonPath('interviews.0.job.id', $davidApplication->job_id);
        $this->actingAs($david)->putJson("/api/interviews/{$interview->id}", [
            'scheduled_at' => now()->addDays(4)->toIso8601String(),
            'type' => 'online',
            'meeting_url' => 'https://meet.example.test/changed',
        ])->assertNotFound();
        $this->actingAs($david)->postJson("/api/interviews/{$interview->id}/cancel")->assertNotFound();

        $this->actingAs($morgan)->getJson('/api/user/interviews')
            ->assertOk()
            ->assertJsonCount(1, 'interviews')
            ->assertJsonPath('interviews.0.job.id', $alexApplication->job_id);
        $this->actingAs($otherSeeker)->getJson('/api/user/interviews')
            ->assertOk()
            ->assertJsonCount(1, 'interviews')
            ->assertJsonPath('interviews.0.job.id', $davidApplication->job_id);
        $this->actingAs($otherSeeker)->putJson("/api/interviews/{$interview->id}", [])->assertForbidden();
    }

    public function test_recruiter_can_only_schedule_for_eligible_applications_they_own(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        $otherRecruiter = User::factory()->create(['role' => 'hr']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $ownedApplication = $this->createAppliedApplication($recruiter, $seeker, 'Owned Role');
        $otherApplication = $this->createAppliedApplication($otherRecruiter, $seeker, 'Another Role');
        $payload = [
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'type' => 'online',
            'meeting_url' => 'https://meet.example.test/interview',
        ];

        $this->actingAs($recruiter)->postJson('/api/interviews', [
            ...$payload,
            'application_id' => $otherApplication->id,
        ])->assertNotFound();
        $inPersonPayload = $payload;
        unset($inPersonPayload['meeting_url']);
        $this->actingAs($recruiter)->postJson('/api/interviews', [
            ...$inPersonPayload,
            'application_id' => $ownedApplication->id,
            'type' => 'in_person',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['location']);
        $this->actingAs($seeker)->postJson('/api/interviews', [
            ...$payload,
            'application_id' => $ownedApplication->id,
        ])->assertForbidden();
    }

    private function createAppliedApplication(User $recruiter, User $seeker, string $title): Application
    {
        $job = Job::create([
            'user_id' => $recruiter->id,
            'title' => $title,
            'description' => 'Role description',
            'department' => 'UI/UX Design',
            'employment_type' => 'Full-time',
            'required_skills_json' => ['Communication'],
            'status' => 'open',
        ]);
        $candidate = Candidate::create([
            'user_id' => $seeker->id,
            'resume_path' => "resumes/{$seeker->id}.pdf",
            'status' => 'done',
            'parsed_name' => 'Morgan Candidate',
            'parsed_email' => $seeker->email,
        ]);

        return Application::create([
            'user_id' => $seeker->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'status' => 'applied',
        ]);
    }
}
