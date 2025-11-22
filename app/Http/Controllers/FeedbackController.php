<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedbackRequest;
use App\Mail\FeedbackReceived;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    public function create(Request $request)
    {
        if ($request->header('HX-Request')) {
            return view('partials.feedback-page');
        }

        return view('feedback.create');
    }

    public function store(FeedbackRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();
        $data['user_id'] = $user->id;
        $data['user_name'] = $user->name;
        $data['user_email'] = $user->email;

        // Use MAIL_FROM_ADDRESS as the admin email if not configured otherwise
        $recipient = config('mail.from.address');

        $this->emailService->sendWithErrorHandling(function () use ($recipient, $data) {
            Mail::to($recipient)->send(new FeedbackReceived($data));
        }, 'feedback_submission');

        if ($request->header('HX-Request')) {
             return view('partials.feedback-success');
        }

        return redirect()->route('dashboard')->with('success', 'Thank you for your feedback!');
    }
}
