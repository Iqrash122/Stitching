<?php

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('studio:owner {email} {--recover}', function () {
    $email = $this->argument('email');
    if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid login email.');

        return 1;
    }
    $password = $this->secret('Owner password (at least 12 characters)');
    if (strlen($password ?? '') < 12) {
        $this->error('Password must contain at least 12 characters.');

        return 1;
    }
    DB::transaction(function () use ($password, $email) {
        DB::table('number_sequences')->where('kind', 'OWNER')->lockForUpdate()->firstOrFail();
        $owner = User::where('is_owner', true)->first();
        if ($owner && ! $this->option('recover')) {
            throw new RuntimeException('Owner exists. Use --recover to reset the password.');
        }if ($owner && $owner->email !== $this->argument('email')) {
            throw new RuntimeException('Recovery email must match the existing owner.');
        }$owner ??= new User;
        $owner->name = 'Owner';
        $owner->email = $email;
        $owner->password = Hash::make($password);
        $owner->is_owner = true;
        $owner->setAttribute('email_verified_at', now());
        $owner->save();
        DB::table('sessions')->where('user_id', $owner->id)->delete();
        $owner->remember_token = null;
        $owner->save();
    });
    $this->info('Owner credentials saved.');
});
Artisan::command('studio:reminders', function () {
    $settings = BusinessSetting::findOrFail(1);
    $cutoff = now()->subDays($settings->recent_days);
    Customer::where('active', true)->whereHas('orders', fn ($q) => $q->whereNotNull('delivered_at'))->whereDoesntHave('orders', fn ($q) => $q->where('delivered_at', '>=', $cutoff))->whereDoesntHave('orders', fn ($q) => $q->whereNotNull('confirmed_at')->whereNotIn('stage', ['Delivered', 'Cancelled']))->each(function ($c) {
        $last = $c->orders()->whereNotNull('delivered_at')->latest('delivered_at')->first();
        FollowUp::firstOrCreate(['reminder_key' => 'inactive:'.$c->id.':'.$last->id], ['customer_id' => $c->id, 'due_at' => now(), 'note' => 'Retention follow-up: review customer history before contacting.']);
    });
    $this->info('Follow-up reminders prepared; no messages sent.');
});
Schedule::command('studio:reminders')->daily()->withoutOverlapping();
