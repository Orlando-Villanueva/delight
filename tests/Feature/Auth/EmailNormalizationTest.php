<?php

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('normalizes email casing when registering a user', function (): void {
    $user = app(CreateNewUser::class)->create([
        'name' => 'Delight Reader',
        'email' => 'Reader@Example.com',
        'password' => 'ValidPass123!',
        'password_confirmation' => 'ValidPass123!',
    ]);

    expect($user->email)->toBe('reader@example.com');
});

it('prevents registration with a case variant of an existing email', function (): void {
    User::factory()->create(['email' => 'Reader@Example.com']);

    expect(fn () => app(CreateNewUser::class)->create([
        'name' => 'Another Reader',
        'email' => 'reader@example.com',
        'password' => 'ValidPass123!',
        'password_confirmation' => 'ValidPass123!',
    ]))->toThrow(ValidationException::class);
});

it('normalizes email casing when updating a profile', function (): void {
    $user = User::factory()->create(['email' => 'reader@example.com']);

    app(UpdateUserProfileInformation::class)->update($user, [
        'name' => $user->name,
        'email' => 'Updated.Reader@Example.com',
    ]);

    expect($user->fresh()->email)->toBe('updated.reader@example.com');
});

it('prevents profile updates to a case variant owned by another user', function (): void {
    User::factory()->create(['email' => 'Owner@Example.com']);
    $user = User::factory()->create(['email' => 'reader@example.com']);

    expect(fn () => app(UpdateUserProfileInformation::class)->update($user, [
        'name' => $user->name,
        'email' => 'owner@example.com',
    ]))->toThrow(ValidationException::class);
});
