<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedbackRequest;
use App\Mail\FeedbackReceived;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FeedbackController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    public function create(): InertiaResponse
    {
        return Inertia::render('Feedback', ['submitUrl' => route('feedback.store')]);
    }

    public function store(FeedbackRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = $request->user();
        $data['user_id'] = $user->id;
        $data['user_name'] = $user->name;
        $data['user_email'] = $user->email;

        // Use configured admin address
        $recipient = config('mail.admin_address');

        $sent = $this->emailService->sendWithErrorHandling(function () use ($recipient, $data) {
            Mail::to($recipient)->send(new FeedbackReceived($data));
        }, 'feedback_submission');

        if (! $sent) {
            return redirect()->route('feedback.create')
                ->withErrors(['submission' => "We couldn't send your feedback. Please try again."]);
        }

        return redirect()->route('feedback.create')->with('status', 'Thank you! Your feedback has been sent.');
    }
}
