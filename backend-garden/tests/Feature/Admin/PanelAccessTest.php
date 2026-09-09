<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

it('redirects a guest to the panel login', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('forbids a regular client', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Client]));

    $this->get('/admin')->assertForbidden();
});

it('forbids a suspended moderator', function (): void {
    $this->actingAs(User::factory()->create([
        'role' => UserRole::Moderator,
        'status' => UserStatus::Suspended,
    ]));

    $this->get('/admin')->assertForbidden();
});

it('lets an active moderator in', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]));

    $this->get('/admin')->assertSuccessful();
});

it('renders the core admin pages for an admin', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $this->get('/admin')->assertSuccessful();
    $this->get('/admin/users')->assertSuccessful();
    $this->get('/admin/reports')->assertSuccessful();
    $this->get('/admin/public-posts')->assertSuccessful();
    $this->get('/admin/feature-flags')->assertSuccessful();
    $this->get('/admin/supports')->assertSuccessful();
    $this->get('/admin/tags')->assertSuccessful();
});
