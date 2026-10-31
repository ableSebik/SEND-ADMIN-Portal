<?php

namespace App\Services;

use App\Models\AdminRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class RenewalReminders
{
    public const DAYS = [21, 14, 7, 3, 2, 1, 0];

    public function send(): void
    {
        $today = CarbonImmutable::now('Africa/Accra')->startOfDay();
        $users = User::where('active', true)->whereIn('role', ['officer', 'admin', 'super_admin'])->get();
        $operations = DB::table('vehicle_operations')->whereNull('superseded_at')->whereBetween('due_on', [$today->toDateString(), $today->addDays(21)->toDateString()])->get();
        foreach ($operations as $operation) {
            $days = (int) $today->diffInDays(CarbonImmutable::parse($operation->due_on, 'Africa/Accra'), false);
            if (! in_array($days, self::DAYS, true)) {
                continue;
            }
            foreach ($users as $user) {
                foreach (['email', 'sms'] as $channel) {
                    DB::transaction(function () use ($operation, $user, $channel, $today, $days) {
                        // Lock the same vehicle as renewal entry; superseded renewals cannot send.
                        $vehicle = AdminRecord::where('module', 'vehicles')->lockForUpdate()->find($operation->vehicle_id);
                        $fresh = DB::table('vehicle_operations')->where('id', $operation->id)->first();
                        if (! $vehicle || $fresh->superseded_at) {
                            return;
                        }
                        $key = ['operation_id' => $operation->id, 'user_id' => $user->id, 'scheduled_on' => $today->toDateString(), 'channel' => $channel];
                        DB::table('renewal_deliveries')->insertOrIgnore([...$key, 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
                        $delivery = DB::table('renewal_deliveries')->where($key)->lockForUpdate()->first();
                        if (in_array($delivery->status, ['Sent', 'Cancelled'], true)) {
                            return;
                        }
                        $message = $vehicle->name.' ('.($vehicle->data['registration'] ?? $vehicle->reference).'): '.FleetOperations::KINDS[$operation->kind].' due '.$operation->due_on.' ('.$days.' days). Please record the renewal in SEND-AMS to stop these reminders.';
                        $status = 'Sent';
                        $error = null;
                        try {
                            if ($channel === 'email') {
                                if (! config('fleet.email_enabled') || in_array(config('mail.default'), ['log', 'array'], true)) {
                                    $status = 'Awaiting setup';
                                    $error = 'Email delivery is not configured.';
                                } else {
                                    Mail::to($user->email)->send(new \App\Mail\VehicleRenewalReminder($message));
                                }
                            } elseif (! $user->phone) {
                                $status = 'Missing phone';
                                $error = 'Add an international SMS number in Users.';
                            } elseif (! config('fleet.sms_url') || ! config('fleet.sms_token')) {
                                $status = 'Awaiting setup';
                                $error = 'SMS delivery is not configured.';
                            } else {
                                Http::timeout(15)->withToken(config('fleet.sms_token'))
                                    ->withHeaders(['Idempotency-Key' => 'fleet-renewal-'.$delivery->id])
                                    ->post(config('fleet.sms_url'), ['to' => $user->phone, 'message' => $message, 'idempotency_key' => 'fleet-renewal-'.$delivery->id])->throw();
                            }
                        } catch (\Throwable $exception) {
                            $status = 'Failed';
                            $error = 'Delivery failed. Check provider configuration and try again.';
                            report($exception);
                        }
                        DB::table('renewal_deliveries')->where('id', $delivery->id)->update(['status' => $status, 'error' => $error,
                            'sent_at' => $status === 'Sent' ? now() : null, 'updated_at' => now()]);
                    });
                }
            }
        }
    }
}
