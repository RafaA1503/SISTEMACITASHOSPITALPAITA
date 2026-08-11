<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TwilioSms
{
    public function configured(): bool
    {
        return filled(config('twilio.account_sid'))
            && filled(config('twilio.auth_token'))
            && (filled(config('twilio.from')) || filled(config('twilio.messaging_service_sid')));
    }

    /** @throws RequestException */
    public function send(string $to, string $message): string
    {
        $payload = ['To' => $this->peruvianPhone($to), 'Body' => $message];

        if ($serviceSid = config('twilio.messaging_service_sid')) {
            $payload['MessagingServiceSid'] = $serviceSid;
        } else {
            $payload['From'] = config('twilio.from');
        }

        $response = Http::asForm()
            ->withBasicAuth(config('twilio.account_sid'), config('twilio.auth_token'))
            ->timeout(15)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('twilio.account_sid').'/Messages.json', $payload)
            ->throw();

        return (string) $response->json('sid');
    }

    public function peruvianPhone(string $phone): string
    {
        $clean = preg_replace('/[^\d+]/', '', $phone);
        if (str_starts_with($clean, '+')) return $clean;
        if (preg_match('/^9\d{8}$/', $clean)) return '+51'.$clean;

        return '+'.$clean;
    }
}
