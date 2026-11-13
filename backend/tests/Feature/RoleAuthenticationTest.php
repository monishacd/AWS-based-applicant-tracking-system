<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recruiter_registration_assigns_role_and_hashes_password_on_server(): void
    {
        $response = $this->postJson('/api/recruiter/register', [
            'name' => 'Recruiter User',
            'email' => 'recruiter@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'company_name' => 'Example Co',
            'role' => 'job_seeker',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'hr')
            ->assertJsonStructure(['user', 'token']);
        $this->assertTrue(Hash::check('secure-password', User::where('email', 'recruiter@example.com')->firstOrFail()->password));
    }

    public function test_job_seeker_registration_assigns_role_and_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/user/register', [
            'name' => 'Job Seeker',
            'email' => 'seeker@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'hr',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'job_seeker');
        $this->assertTrue(Hash::check('secure-password', User::where('email', 'seeker@example.com')->firstOrFail()->password));

        $this->postJson('/api/user/register', [
            'name' => 'Another Seeker',
            'email' => 'another@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_role_specific_login_rejects_the_other_account_type(): void
    {
        $recruiter = User::factory()->create([
            'email' => 'recruiter@example.com',
            'password' => Hash::make('secure-password'),
            'role' => 'hr',
        ]);
        $seeker = User::factory()->create([
            'email' => 'seeker@example.com',
            'password' => Hash::make('secure-password'),
            'role' => 'job_seeker',
        ]);

        $this->postJson('/api/user/login', ['email' => $recruiter->email, 'password' => 'secure-password'])
            ->assertUnauthorized()->assertJsonMissingPath('token');
        $this->postJson('/api/recruiter/login', ['email' => $seeker->email, 'password' => 'secure-password'])
            ->assertUnauthorized()->assertJsonMissingPath('token');
        $this->postJson('/api/user/login', ['email' => $seeker->email, 'password' => 'secure-password'])
            ->assertOk()->assertJsonPath('user.role', 'job_seeker');
    }

    public function test_job_seekers_are_denied_recruiter_api_routes(): void
    {
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $token = $seeker->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/jobs')
            ->assertForbidden();
    }

    public function test_recruiter_can_use_recruiter_api(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        $token = $recruiter->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonPath('jobs', []);
    }

    public function test_public_job_board_returns_open_jobs_only(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        Job::create([
            'user_id' => $recruiter->id,
            'title' => 'Open Designer Role',
            'description' => 'An open role.',
            'department' => 'Software Engineering',
            'required_skills_json' => ['Design'],
            'status' => 'open',
        ]);
        Job::create([
            'user_id' => $recruiter->id,
            'title' => 'Closed Designer Role',
            'description' => 'A closed role.',
            'department' => 'Software Engineering',
            'required_skills_json' => [],
            'status' => 'closed',
        ]);

        $this->getJson('/api/public/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Open Designer Role');
    }

    public function test_recruiter_can_create_and_filter_jobs_by_department(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);

        $this->actingAs($recruiter)->postJson('/api/jobs', [
            'title' => 'Data Analyst',
            'description' => 'Analyze product and operational data.',
            'department' => 'Data & Analytics',
            'employment_type' => 'Full-time',
            'required_skills' => ['SQL', 'Python'],
            'experience_requirements' => 'Entry level',
        ])->assertCreated()
            ->assertJsonPath('job.department', 'Data & Analytics')
            ->assertJsonPath('job.employment_type', 'Full-time');

        $this->getJson('/api/public/jobs?department=Data%20%26%20Analytics')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Data Analyst');
    }

    public function test_removed_departments_are_rejected_for_job_creation_and_updates(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        $baseJob = [
            'title' => 'Sample Role',
            'description' => 'A sample role.',
            'department' => 'Software Engineering',
            'employment_type' => 'Full-time',
        ];

        foreach (['Finance', 'Product', 'Customer Success', 'Operations', 'Legal', 'Internships', 'Other'] as $department) {
            $this->actingAs($recruiter)->postJson('/api/jobs', [
                ...$baseJob,
                'department' => $department,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('department');
        }

        $job = Job::create([
            'user_id' => $recruiter->id,
            ...$baseJob,
            'required_skills_json' => [],
            'status' => 'open',
        ]);

        $this->actingAs($recruiter)->putJson("/api/jobs/{$job->id}", [
            'department' => 'Product',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('department');
    }

    public function test_public_job_board_hides_jobs_with_removed_departments(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        Job::create([
            'user_id' => $recruiter->id,
            'title' => 'Legacy Product Job',
            'description' => 'A legacy job in a removed department.',
            'department' => 'Product',
            'required_skills_json' => [],
            'status' => 'open',
        ]);

        $this->getJson('/api/public/jobs')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
