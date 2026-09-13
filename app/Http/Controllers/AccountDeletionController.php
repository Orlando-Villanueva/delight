<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitAccountDeletionRequest;
use App\Mail\AccountDeletionRequested;
use App\Mail\AccountDeletionVerification;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AccountDeletionController extends Controller
{
    public function __construct(private EmailService $emailService) {}

    public function create(): View
    {
        return view('account-deletion.create');
    }

    public function store(SubmitAccountDeletionRequest $request): RedirectResponse
    {
        $email = $request->validated('email');
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user !== null) {
            $verificationUrl = URL::temporarySignedRoute(
                'account-deletion.confirm',
                now()->addHour(),
                [
                    'user' => $user,
                    'email_hash' => $this->emailHash($user->email),
                ],
            );

            $this->emailService->sendWithErrorHandling(function () use ($user, $verificationUrl): void {
                Mail::to($user->email)->send(new AccountDeletionVerification($verificationUrl));
            }, 'account-deletion-verification');
        }

        return redirect()
            ->route('account-deletion.create')
            ->with('account_deletion_status', 'If this email belongs to a Delight account, we sent a confirmation link. Check your inbox and spam folder.');
    }

    public function confirm(User $user, Request $request): View
    {
        abort_unless($this->hasMatchingEmailHash($user, $request), 403);

        return view('account-deletion.confirm', [
            'confirmationUrl' => $request->fullUrl(),
            'isAlreadyConfirmed' => Cache::has($this->confirmationCacheKey($request)),
        ]);
    }

    public function submitConfirmation(User $user, Request $request): RedirectResponse
    {
        abort_unless($this->hasMatchingEmailHash($user, $request), 403);

        $confirmationCacheKey = $this->confirmationCacheKey($request);

        if (Cache::has($confirmationCacheKey)) {
            return redirect()
                ->route('account-deletion.received')
                ->with('account_deletion_received', true);
        }

        $wasSent = $this->emailService->sendWithErrorHandling(function () use ($user): void {
            Mail::to(config('mail.support_address'))->send(new AccountDeletionRequested(
                userId: $user->id,
                userName: $user->name,
                userEmail: $user->email,
            ));
        }, 'account-deletion-request');

        if (! $wasSent) {
            return back()->withErrors([
                'confirmation' => 'We could not record your request right now. Please try again. If the problem continues, contact support.',
            ]);
        }

        Cache::put($confirmationCacheKey, true, now()->addDay());

        return redirect()
            ->route('account-deletion.received')
            ->with('account_deletion_received', true);
    }

    public function received(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('account_deletion_received', false)) {
            return redirect()->route('account-deletion.create');
        }

        return view('account-deletion.received');
    }

    private function confirmationCacheKey(Request $request): string
    {
        return 'account-deletion-confirmed:'.hash('sha256', (string) $request->query('signature'));
    }

    private function hasMatchingEmailHash(User $user, Request $request): bool
    {
        return hash_equals(
            $this->emailHash($user->email),
            (string) $request->query('email_hash'),
        );
    }

    private function emailHash(string $email): string
    {
        return hash('sha256', Str::of($email)->trim()->lower()->toString());
    }
}
