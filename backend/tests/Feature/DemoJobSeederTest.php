<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Database\Seeders\DemoJobSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoJobSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_50_open_jobs_across_all_departments_without_duplicates(): void
    {
        User::factory()->create(['role' => 'hr']);

        $seeder = app(DemoJobSeeder::class);
        $seeder->run();
        $seeder->run();

        $jobs = Job::where('title', 'like', 'Demo:%');
        $this->assertSame(50, (clone $jobs)->count());
        $this->assertSame(50, (clone $jobs)->where('status', 'open')->count());
        $this->assertSame(50, (clone $jobs)->distinct('title')->count('title'));
        $this->assertSame(5, Job::where('department', 'Software Engineering')->count());
        $this->assertSame(5, Job::where('department', 'DevOps & Cloud')->count());
        $this->assertSame(5, Job::where('department', 'Data & Analytics')->count());
        $this->assertSame(5, Job::where('department', 'AI & Machine Learning')->count());
        $this->assertSame(5, Job::where('department', 'UI/UX Design')->count());
        $this->assertSame(5, Job::where('department', 'Sales')->count());
        $this->assertSame(5, Job::where('department', 'Human Resources')->count());
        $this->assertSame(5, Job::where('department', 'Cybersecurity')->count());
        $this->assertSame(5, Job::where('department', 'Quality Assurance')->count());
        $this->assertSame(5, Job::where('department', 'Marketing')->count());
        $this->assertSame(10, (clone $jobs)->distinct('department')->count('department'));
    }

    public function test_it_closes_obsolete_demo_jobs_without_deleting_them(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        $legacyJob = Job::create([
            'user_id' => $recruiter->id,
            'title' => 'Demo: Product Manager - FinTech',
            'description' => 'Legacy demo listing.',
            'department' => 'Product',
            'required_skills_json' => [],
            'status' => 'open',
        ]);

        app(DemoJobSeeder::class)->run();

        $this->assertDatabaseHas('jobs', [
            'id' => $legacyJob->id,
            'department' => 'Product',
            'status' => 'closed',
        ]);
        $this->assertSame(50, Job::where('title', 'like', 'Demo:%')->where('status', 'open')->count());
    }

    public function test_it_can_seed_all_demo_jobs_for_a_specific_recruiter(): void
    {
        $recruiter = User::factory()->create(['role' => 'hr']);
        $otherRecruiter = User::factory()->create(['role' => 'hr']);
        $seeder = app(DemoJobSeeder::class);

        $seeder->seedFor($recruiter);
        $seeder->seedFor($recruiter);

        $this->assertSame(50, Job::where('user_id', $recruiter->id)->where('title', 'like', 'Demo:%')->count());
        $this->assertSame(50, Job::where('user_id', $recruiter->id)->where('title', 'like', 'Demo:%')->where('status', 'open')->count());
        $this->assertSame(0, Job::where('user_id', $otherRecruiter->id)->count());
        $this->assertSame(5, Job::where('user_id', $recruiter->id)->where('department', 'Software Engineering')->count());
        $this->assertSame(5, Job::where('user_id', $recruiter->id)->where('department', 'DevOps & Cloud')->count());
        $this->assertSame(5, Job::where('user_id', $recruiter->id)->where('department', 'Sales')->count());
    }
}
