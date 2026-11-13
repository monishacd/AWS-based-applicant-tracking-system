<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Interview;
use App\Notifications\InterviewUpdateNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InterviewController extends Controller
{
    public function recruiterIndex(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:scheduled,rescheduled,completed,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = Interview::with($this->interviewRelations())
            ->whereHas('application.job', fn ($jobs) => $jobs->accessibleToRecruiter($request->user()))
            ->when($request->query('search'), function ($interviews, $search) {
                $interviews->whereHas('application', function ($applications) use ($search) {
                    $applications->whereHas('user', fn ($users) => $users
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('candidate', fn ($candidates) => $candidates
                            ->where('parsed_name', 'like', "%{$search}%")
                            ->orWhere('parsed_email', 'like', "%{$search}%"))
                        ->orWhereHas('job', fn ($jobs) => $jobs->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('status'), fn ($interviews, $status) => $interviews->where('status', $status))
            ->when($request->query('from'), fn ($interviews, $date) => $interviews->whereDate('scheduled_at', '>=', $date))
            ->when($request->query('to'), fn ($interviews, $date) => $interviews->whereDate('scheduled_at', '<=', $date))
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'interviews' => $query->map(fn (Interview $interview) => $this->present($interview, true)),
        ]);
    }

    public function seekerIndex(Request $request)
    {
        $interviews = Interview::with($this->interviewRelations())
            ->whereHas('application', fn ($applications) => $applications->where('user_id', $request->user()->id))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Interview $interview) => $this->present($interview, false));

        $updates = $request->user()->notifications()
            ->where('type', InterviewUpdateNotification::class)
            ->latest()
            ->limit(30)
            ->get(['id', 'data', 'read_at', 'created_at'])
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]);

        return response()->json(['interviews' => $interviews, 'updates' => $updates]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->interviewRules());
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $application = Application::with(['candidate', 'user', 'job'])
            ->whereKey($validator->validated()['application_id'])
            ->whereHas('job', fn ($jobs) => $jobs->accessibleToRecruiter($request->user()))
            ->whereHas('candidate', fn ($candidates) => $candidates->whereColumn('candidates.user_id', 'applications.user_id'))
            ->firstOrFail();
        abort_if($application->status === 'rejected' || $application->offer_declined_at, 422, 'This application is no longer eligible for an interview.');

        $interview = DB::transaction(function () use ($application, $validator) {
            $interview = $application->interviews()->create([
                ...$validator->safe()->except('application_id'),
                'status' => 'scheduled',
            ]);
            $application->update([
                'status' => 'interview',
                'interview_at' => $interview->scheduled_at,
                'interview_notes' => $interview->instructions,
                'shortlisted_at' => $application->shortlisted_at ?? now(),
            ]);

            return $interview;
        });

        $interview->load($this->interviewRelations());
        $application->user->notify(new InterviewUpdateNotification($interview, 'scheduled'));

        return response()->json([
            'interview' => $this->present($interview, true),
            'message' => 'Interview scheduled. The job seeker can see the details in My Interviews.',
        ], 201);
    }

    public function update(Request $request, Interview $interview)
    {
        $this->authorizeRecruiter($request, $interview);
        abort_if(in_array($interview->status, ['cancelled', 'completed'], true), 422, 'Cancelled or completed interviews cannot be edited.');

        $validator = Validator::make($request->all(), $this->interviewRules(false));
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $updates = $validator->validated();
        $rescheduled = array_key_exists('scheduled_at', $updates)
            && ! $interview->scheduled_at->equalTo($updates['scheduled_at']);

        DB::transaction(function () use ($interview, $updates, $rescheduled) {
            if ($rescheduled) {
                $updates['status'] = 'rescheduled';
            }
            $interview->update($updates);
            $this->syncApplication($interview->application);
        });

        $interview->refresh()->load($this->interviewRelations());
        $event = $rescheduled ? 'rescheduled' : 'updated';
        $interview->application->user->notify(new InterviewUpdateNotification($interview, $event));

        return response()->json([
            'interview' => $this->present($interview, true),
            'message' => $rescheduled
                ? 'Interview rescheduled. The job seeker has an in-app update.'
                : 'Interview updated. The job seeker has an in-app update.',
        ]);
    }

    public function updateStatus(Request $request, Interview $interview)
    {
        $this->authorizeRecruiter($request, $interview);
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:completed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        abort_if($interview->status === 'cancelled', 422, 'A cancelled interview cannot be reopened.');

        DB::transaction(function () use ($interview, $validator) {
            $interview->update(['status' => $validator->validated()['status']]);
            $this->syncApplication($interview->application);
        });

        return response()->json(['interview' => $this->present($interview->fresh()->load($this->interviewRelations()), true)]);
    }

    public function cancel(Request $request, Interview $interview)
    {
        $this->authorizeRecruiter($request, $interview);
        abort_if(in_array($interview->status, ['cancelled', 'completed'], true), 422, 'This interview can no longer be cancelled.');

        DB::transaction(function () use ($interview) {
            $interview->update(['status' => 'cancelled']);
            $this->syncApplication($interview->application);
        });

        $interview->refresh()->load($this->interviewRelations());
        $interview->application->user->notify(new InterviewUpdateNotification($interview, 'cancelled'));

        return response()->json([
            'interview' => $this->present($interview, true),
            'message' => 'Interview cancelled. The job seeker has an in-app update.',
        ]);
    }

    public function updateOutcome(Request $request, Interview $interview)
    {
        $this->authorizeRecruiter($request, $interview);
        abort_if($interview->status === 'cancelled', 422, 'A cancelled interview cannot have an outcome.');

        $validator = Validator::make($request->all(), [
            'outcome' => ['required', 'in:selected,rejected,next_round'],
            'published' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        DB::transaction(function () use ($interview, $data) {
            $interview->update([
                'outcome' => $data['outcome'],
                'outcome_published_at' => $data['published'] ? ($interview->outcome_published_at ?? now()) : null,
            ]);
            if ($data['published']) {
                $interview->application->update([
                    'status' => match ($data['outcome']) {
                        'selected' => 'cleared',
                        'rejected' => 'rejected',
                        'next_round' => 'interview',
                    },
                ]);
            }
        });

        $interview->refresh()->load($this->interviewRelations());
        if ($data['published']) {
            $interview->application->user->notify(new InterviewUpdateNotification($interview, 'outcome_published'));
        }

        return response()->json(['interview' => $this->present($interview, true)]);
    }

    private function interviewRules(bool $creating = true): array
    {
        return [
            ...($creating ? ['application_id' => ['required', 'integer', 'exists:applications,id']] : []),
            'scheduled_at' => ['required', 'date', 'after:now'],
            'type' => ['required', 'in:online,in_person'],
            'meeting_url' => ['required_if:type,online', 'prohibited_unless:type,online', 'nullable', 'url', 'max:2048'],
            'location' => ['required_if:type,in_person', 'prohibited_unless:type,in_person', 'nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ];
    }

    private function authorizeRecruiter(Request $request, Interview $interview): void
    {
        abort_unless(
            $interview->application()->whereHas('job', fn ($jobs) => $jobs->accessibleToRecruiter($request->user()))->exists(),
            404,
        );
    }

    private function interviewRelations(): array
    {
        return [
            'application.candidate:id,user_id,parsed_name,parsed_email',
            'application.user:id,name,email',
            'application.job:id,user_id,title',
            'application.job.user:id,name,company_name',
        ];
    }

    private function present(Interview $interview, bool $recruiter): array
    {
        $application = $interview->application;
        $candidate = $application->candidate;
        $recruiterUser = $application->job->user;

        return [
            'id' => $interview->id,
            'application_id' => $application->id,
            'scheduled_at' => $interview->scheduled_at,
            'type' => $interview->type,
            'meeting_url' => $interview->meeting_url,
            'location' => $interview->location,
            'instructions' => $interview->instructions,
            'status' => $interview->status,
            'outcome' => $recruiter || $interview->outcome_published_at ? $interview->outcome : null,
            'outcome_published_at' => $recruiter || $interview->outcome_published_at ? $interview->outcome_published_at : null,
            'created_at' => $interview->created_at,
            'updated_at' => $interview->updated_at,
            'candidate' => [
                'id' => $candidate->id,
                'name' => $candidate->parsed_name ?: $application->user->name,
                'email' => $candidate->parsed_email ?: $application->user->email,
            ],
            'job' => [
                'id' => $application->job->id,
                'title' => $application->job->title,
            ],
            'recruiter' => [
                'name' => $recruiterUser->name,
                'company_name' => $recruiterUser->company_name,
            ],
        ];
    }

    private function syncApplication(Application $application): void
    {
        $nextInterview = $application->interviews()
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->orderByDesc('scheduled_at')
            ->first();

        $application->update([
            'interview_at' => $nextInterview?->scheduled_at,
            'interview_notes' => $nextInterview?->instructions,
        ]);
    }
}
