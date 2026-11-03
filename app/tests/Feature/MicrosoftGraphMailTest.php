<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MicrosoftGraphMailTest extends TestCase
{
    private function configureMailer(): void
    {
        config(['mail.default' => 'microsoft_graph', 'mail.from.address' => 'admin@example.org',
            'mail.mailers.microsoft_graph' => ['transport' => 'microsoft_graph', 'tenant_id' => 'tenant',
                'client_id' => 'client', 'client_secret' => 'test-secret', 'sender' => 'admin@example.org']]);
        Http::preventStrayRequests();
    }

    public function test_mail_is_submitted_with_oauth_and_message_contents(): void
    {
        $this->configureMailer();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token']),
            'graph.microsoft.com/*' => Http::response('', 202)]);
        Mail::html('<p>Reset link</p>', function ($message) {
            $message->to('recipient@example.org')->bcc('copy@example.org')->subject('Reset password')->attachData('file contents', 'test.txt');
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMail')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'recipient@example.org'
            && $request['message']['bccRecipients'][0]['emailAddress']['address'] === 'copy@example.org'
            && $request['message']['body']['content'] === '<p>Reset link</p>'
            && base64_decode($request['message']['attachments'][0]['contentBytes']) === 'file contents');
    }

    public function test_permission_failure_is_reported_without_response_secrets(): void
    {
        $this->configureMailer();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token']),
            'graph.microsoft.com/*' => Http::response(['error' => 'sensitive response'], 403)]);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Microsoft Graph mail submission failed (HTTP 403)');
        Mail::raw('Reset link', fn ($message) => $message->to('recipient@example.org')->subject('Reset'));
    }

    public function test_missing_secret_fails_before_any_network_request(): void
    {
        $this->configureMailer();
        config(['mail.mailers.microsoft_graph.client_secret' => '']);
        try {
            Mail::raw('Reset', fn ($message) => $message->to('recipient@example.org'));
            $this->fail('Expected missing credential error.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('missing client_secret', $exception->getMessage());
            Http::assertNothingSent();
        }
    }
}
