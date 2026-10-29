<?php

use App\Models\User;

Artisan::command('send:admin {email} {--name=Administrator}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('A valid email is required.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Account already exists.');

        return 1;
    }
    $password = $this->secret('Choose a password (at least 8 characters)');
    if (strlen($password ?? '') < 8) {
        $this->error('Password must contain at least 8 characters.');

        return 1;
    }
    $user = new User;
    $user->forceFill(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'super_admin', 'active' => true])->save();
    $this->info('Administrator created.');
})->purpose('Create an initial administrator without hard-coded credentials');

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
