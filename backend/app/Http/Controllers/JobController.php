<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    private const DEPARTMENTS = [
        'Software Engineering',
        'DevOps & Cloud',
        'Data & Analytics',
        'AI & Machine Learning',
        'UI/UX Design',
        'Cybersecurity',
        'Quality Assurance',
        'Human Resources',
        'Sales',
        'Marketing',
    ];

    public function publicIndex(Request $request)
    {
        $viewer = $request->user('sanctum');
        $jobIdRange = $viewer?->jobIdRange();
        $query = Job::where('status', 'open')
            ->whereIn('department', self::DEPARTMENTS)
            ->with('user:id,company_name');
        if ($jobIdRange) {
            $query->whereBetween('id', $jobIdRange);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($jobs) use ($search) {
                $jobs->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%')
                    ->orWhere('experience_requirements', 'like', '%'.$search.'%');
            });
        }
        if ($department = $request->query('department')) {
            $query->where('department', $department);
        }
        if ($skills = $request->query('skills')) {
            $skillList = array_filter(array_map('trim', explode(',', (string) $skills)));
            foreach ($skillList as $skill) {
                $query->whereJsonContains('required_skills_json', $skill);
            }
        }

        $maxPerPage = $jobIdRange ? 2200 : 50;

        return response()->json($query->orderByDesc('created_at')->paginate(min($maxPerPage, max(1, (int) $request->query('per_page', 20)))));
    }

    public function showPublic(Job $job)
    {
        if ($job->status !== 'open') {
            return response()->json(['message' => 'Job not found'], 404);
        }

        return response()->json(['job' => $job->load('user:id,company_name')]);
    }

    public function index(Request $request)
    {
        $query = Job::accessibleToRecruiter($request->user());
        if ($jobIdRange = $request->user()->jobIdRange()) {
            $query->whereBetween('id', $jobIdRange);
        }
        $jobs = $query
            ->withCount(['matches', 'applications'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['jobs' => $jobs]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'department' => ['required', 'string', Rule::in(self::DEPARTMENTS)],
            'employment_type' => ['required', 'string', 'in:Full-time,Part-time,Contract,Internship,Temporary'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:80'],
            'experience_requirements' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $job = Job::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'department' => $request->department,
            'employment_type' => $request->employment_type,
            'required_skills_json' => $request->required_skills ?? [],
            'experience_requirements' => $request->experience_requirements,
            'status' => $request->status ?? 'open',
        ]);

        return response()->json(['job' => $job], 201);
    }

    public function update(Request $request, Job $job)
    {
        if (! Job::accessibleToRecruiter($request->user())->whereKey($job->getKey())->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'department' => ['sometimes', 'required', 'string', Rule::in(self::DEPARTMENTS)],
            'employment_type' => ['sometimes', 'required', 'string', 'in:Full-time,Part-time,Contract,Internship,Temporary'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:80'],
            'experience_requirements' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $job->update([
            'title' => $request->title ?? $job->title,
            'description' => $request->description ?? $job->description,
            'department' => $request->department ?? $job->department,
            'employment_type' => $request->employment_type ?? $job->employment_type,
            'required_skills_json' => $request->has('required_skills')
                ? ($request->required_skills ?? [])
                : $job->required_skills_json,
            'experience_requirements' => $request->experience_requirements ?? $job->experience_requirements,
            'status' => $request->status ?? $job->status,
        ]);

        return response()->json(['job' => $job]);
    }

    public function destroy(Request $request, Job $job)
    {
        if (! Job::accessibleToRecruiter($request->user())->whereKey($job->getKey())->exists()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $job->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
