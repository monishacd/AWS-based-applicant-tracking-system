<?php

namespace Database\Seeders;

use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoJobSeeder extends Seeder
{
    private const JOBS = [
        ['title' => 'Software Engineer', 'department' => 'Software Engineering', 'skills' => ['PHP', 'Laravel', 'MySQL']],
        ['title' => 'Frontend Developer', 'department' => 'Software Engineering', 'skills' => ['JavaScript', 'React', 'CSS']],
        ['title' => 'Backend Developer', 'department' => 'Software Engineering', 'skills' => ['PHP', 'REST APIs', 'PostgreSQL']],
        ['title' => 'Full Stack Developer', 'department' => 'Software Engineering', 'skills' => ['TypeScript', 'React', 'Node.js']],
        ['title' => 'Mobile App Developer', 'department' => 'Software Engineering', 'skills' => ['Kotlin', 'Android', 'REST APIs']],
        ['title' => 'DevOps Engineer', 'department' => 'DevOps & Cloud', 'skills' => ['Docker', 'AWS', 'CI/CD']],
        ['title' => 'Cloud Solutions Architect', 'department' => 'DevOps & Cloud', 'skills' => ['AWS', 'Terraform', 'Networking']],
        ['title' => 'Site Reliability Engineer', 'department' => 'DevOps & Cloud', 'skills' => ['Linux', 'Kubernetes', 'Observability']],
        ['title' => 'Platform Engineer', 'department' => 'DevOps & Cloud', 'skills' => ['Kubernetes', 'Go', 'CI/CD']],
        ['title' => 'Cloud Engineer', 'department' => 'DevOps & Cloud', 'skills' => ['AWS', 'Azure', 'Terraform']],
        ['title' => 'Data Analyst', 'department' => 'Data & Analytics', 'skills' => ['SQL', 'Python', 'Tableau']],
        ['title' => 'Data Engineer', 'department' => 'Data & Analytics', 'skills' => ['Python', 'Airflow', 'Spark']],
        ['title' => 'Analytics Engineer', 'department' => 'Data & Analytics', 'skills' => ['dbt', 'SQL', 'Data Modeling']],
        ['title' => 'Business Intelligence Analyst', 'department' => 'Data & Analytics', 'skills' => ['Power BI', 'SQL', 'Excel']],
        ['title' => 'Data Quality Analyst', 'department' => 'Data & Analytics', 'skills' => ['Data Quality', 'SQL', 'Data Governance']],
        ['title' => 'Data Scientist', 'department' => 'AI & Machine Learning', 'skills' => ['Python', 'Statistics', 'scikit-learn']],
        ['title' => 'Machine Learning Engineer', 'department' => 'AI & Machine Learning', 'skills' => ['Python', 'PyTorch', 'MLOps']],
        ['title' => 'NLP Engineer', 'department' => 'AI & Machine Learning', 'skills' => ['Python', 'NLP', 'Transformers']],
        ['title' => 'Computer Vision Engineer', 'department' => 'AI & Machine Learning', 'skills' => ['Python', 'OpenCV', 'PyTorch']],
        ['title' => 'MLOps Engineer', 'department' => 'AI & Machine Learning', 'skills' => ['MLflow', 'Docker', 'Kubernetes']],
        ['title' => 'UI/UX Designer', 'department' => 'UI/UX Design', 'skills' => ['Figma', 'Prototyping', 'Accessibility']],
        ['title' => 'UX Researcher', 'department' => 'UI/UX Design', 'skills' => ['User Interviews', 'Usability Testing', 'Research']],
        ['title' => 'Interaction Designer', 'department' => 'UI/UX Design', 'skills' => ['Interaction Design', 'Figma', 'Prototyping']],
        ['title' => 'Visual Designer', 'department' => 'UI/UX Design', 'skills' => ['Visual Design', 'Typography', 'Figma']],
        ['title' => 'Design Systems Designer', 'department' => 'UI/UX Design', 'skills' => ['Figma', 'Design Systems', 'Accessibility']],
        ['title' => 'Sales Development Representative', 'department' => 'Sales', 'skills' => ['Prospecting', 'CRM', 'Communication']],
        ['title' => 'Account Executive', 'department' => 'Sales', 'skills' => ['Negotiation', 'Salesforce', 'Relationship Management']],
        ['title' => 'Enterprise Account Executive', 'department' => 'Sales', 'skills' => ['Enterprise Sales', 'Forecasting', 'Negotiation']],
        ['title' => 'Sales Engineer', 'department' => 'Sales', 'skills' => ['Technical Sales', 'Solution Design', 'Presentations']],
        ['title' => 'Business Development Representative', 'department' => 'Sales', 'skills' => ['Lead Generation', 'Outreach', 'CRM']],
        ['title' => 'Human Resources Specialist', 'department' => 'Human Resources', 'skills' => ['Recruiting', 'Onboarding', 'HRIS']],
        ['title' => 'Talent Acquisition Specialist', 'department' => 'Human Resources', 'skills' => ['Sourcing', 'Interviewing', 'Applicant Tracking']],
        ['title' => 'HR Business Partner', 'department' => 'Human Resources', 'skills' => ['Employee Relations', 'Workforce Planning', 'Coaching']],
        ['title' => 'Learning and Development Specialist', 'department' => 'Human Resources', 'skills' => ['Training', 'Facilitation', 'Learning Management']],
        ['title' => 'Compensation and Benefits Analyst', 'department' => 'Human Resources', 'skills' => ['Compensation', 'Benefits', 'Data Analysis']],
        ['title' => 'Cybersecurity Analyst', 'department' => 'Cybersecurity', 'skills' => ['SIEM', 'Incident Response', 'Security']],
        ['title' => 'Security Engineer', 'department' => 'Cybersecurity', 'skills' => ['Security Engineering', 'Network Security', 'Cloud Security']],
        ['title' => 'Information Security Analyst', 'department' => 'Cybersecurity', 'skills' => ['Risk Assessment', 'Compliance', 'Security Audits']],
        ['title' => 'Cloud Security Engineer', 'department' => 'Cybersecurity', 'skills' => ['AWS Security', 'IAM', 'Threat Detection']],
        ['title' => 'SOC Analyst', 'department' => 'Cybersecurity', 'skills' => ['SIEM', 'Threat Hunting', 'Incident Response']],
        ['title' => 'QA Automation Engineer', 'department' => 'Quality Assurance', 'skills' => ['Playwright', 'Test Automation', 'CI/CD']],
        ['title' => 'Quality Assurance Engineer', 'department' => 'Quality Assurance', 'skills' => ['Test Plans', 'API Testing', 'Defect Tracking']],
        ['title' => 'Software Test Engineer', 'department' => 'Quality Assurance', 'skills' => ['Software Testing', 'Test Cases', 'Bug Tracking']],
        ['title' => 'Performance Test Engineer', 'department' => 'Quality Assurance', 'skills' => ['Load Testing', 'JMeter', 'Performance Analysis']],
        ['title' => 'API Test Engineer', 'department' => 'Quality Assurance', 'skills' => ['API Testing', 'Postman', 'Automation']],
        ['title' => 'Digital Marketing Specialist', 'department' => 'Marketing', 'skills' => ['SEO', 'Analytics', 'Content']],
        ['title' => 'SEO Specialist', 'department' => 'Marketing', 'skills' => ['SEO', 'Keyword Research', 'Google Analytics']],
        ['title' => 'Content Marketing Manager', 'department' => 'Marketing', 'skills' => ['Content Strategy', 'Writing', 'Campaigns']],
        ['title' => 'Brand Marketing Specialist', 'department' => 'Marketing', 'skills' => ['Brand Strategy', 'Campaigns', 'Market Research']],
        ['title' => 'Growth Marketing Analyst', 'department' => 'Marketing', 'skills' => ['Growth Marketing', 'A/B Testing', 'Analytics']],
    ];

    private const SECTORS = [
        'FinTech',
        'HealthTech',
        'Education',
        'E-commerce',
        'Climate Technology',
        'Media',
        'Cybersecurity',
        'Logistics',
        'Enterprise Software',
        'Digital Services',
    ];

    public function run(): void
    {
        $recruiters = User::query()
            ->whereIn('role', ['hr', 'recruiter'])
            ->orderBy('id')
            ->get();

        if ($recruiters->isEmpty()) {
            throw new RuntimeException('Create a recruiter account before running DemoJobSeeder.');
        }

        $jobIds = [];
        foreach (self::JOBS as $index => $jobDetails) {
            $recruiter = $recruiters[$index % $recruiters->count()];
            $jobIds[] = $this->createDemoJob($recruiter, $jobDetails, $index)->id;
        }

        $this->closeObsoleteDemoJobs($jobIds);
        $this->command?->info(count(self::JOBS).' open demo job listings are available; obsolete demo listings were closed.');
    }

    public function seedFor(User $recruiter): void
    {
        if (! in_array($recruiter->role, ['hr', 'recruiter'], true)) {
            throw new RuntimeException('Demo jobs can only be seeded for a recruiter account.');
        }

        $jobIds = [];
        foreach (self::JOBS as $index => $jobDetails) {
            $jobIds[] = $this->createDemoJob($recruiter, $jobDetails, $index)->id;
        }

        $this->closeObsoleteDemoJobs($jobIds, $recruiter->id);
        $this->command?->info(count(self::JOBS)." open demo job listings are available for {$recruiter->name}.");
    }

    private function createDemoJob(User $recruiter, array $jobDetails, int $index): Job
    {
        $sector = self::SECTORS[$index % count(self::SECTORS)];
        $job = Job::firstOrNew([
            'user_id' => $recruiter->id,
            'title' => "Demo: {$jobDetails['title']}",
        ]);
        $job->fill([
            'description' => "Join a {$sector} team as a {$jobDetails['title']}. Collaborate with colleagues, deliver practical solutions, communicate progress clearly, and help improve the products and services used by customers. This is sample data for local project demonstrations.",
            'required_skills_json' => $jobDetails['skills'],
            'experience_requirements' => ['Entry level', '1-3 years', '3-5 years', '5+ years'][$index % 4],
            'department' => $jobDetails['department'],
            'employment_type' => 'Full-time',
            'status' => 'open',
        ]);
        $job->save();

        return $job;
    }

    private function closeObsoleteDemoJobs(array $jobIds, ?int $recruiterId = null): void
    {
        $query = Job::query()
            ->where('title', 'like', 'Demo:%')
            ->where('status', 'open')
            ->whereNotIn('id', $jobIds);

        if ($recruiterId !== null) {
            $query->where('user_id', $recruiterId);
        }

        $query->update(['status' => 'closed']);
    }
}
