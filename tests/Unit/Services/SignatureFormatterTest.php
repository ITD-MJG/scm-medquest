<?php

use App\Models\User;
use App\Support\SignatureFormatter;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['Supply Chain Officer', 'Security', 'QA Staff'] as $role) {
        Role::create(['name' => $role]);
    }
});

it('formats a signature as initial plus signing date', function () {
    $user = User::factory()->create(['initial' => 'IAN']);
    $user->assignRole('Supply Chain Officer');

    expect(SignatureFormatter::format($user, Carbon::parse('2026-08-05 10:50:00')))
        ->toBe('IAN 05 AUG 2026');
});

it('shows the full name for a Security user', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso', 'initial' => 'BS']);
    $user->assignRole('Security');

    expect(SignatureFormatter::format($user, Carbon::parse('2026-08-05 10:50:00')))
        ->toBe('Budi Santoso');
});

it('uses the signing date and not the record date', function () {
    $user = User::factory()->create(['initial' => 'IAN']);
    $user->assignRole('QA Staff');

    // Spec §6: formatPicSignature() stamps created_at; this must not.
    expect(SignatureFormatter::format($user, Carbon::parse('2026-09-01 08:00:00')))
        ->toBe('IAN 01 SEP 2026');
});
