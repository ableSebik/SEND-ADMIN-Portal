<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(private array $settings)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }

    protected function doSend(SentMessage $message): void
    {
        foreach (['tenant_id', 'client_id', 'client_secret', 'sender'] as $key) {
            if (empty($this->settings[$key])) {
                throw new TransportException('Microsoft Graph mail configuration is missing '.$key.'.');
            }
        }
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            throw new TransportException('Microsoft Graph requires an email message.');
        }
        $token = Http::asForm()->timeout(20)->post(
            'https://login.microsoftonline.com/'.rawurlencode($this->settings['tenant_id']).'/oauth2/v2.0/token',
            ['client_id' => $this->settings['client_id'], 'client_secret' => $this->settings['client_secret'],
                'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials']
        );
        if (! $token->successful() || ! is_string($token->json('access_token'))) {
            throw new TransportException('Microsoft Graph authentication failed (HTTP '.$token->status().'). Check the application credentials.');
        }
        $addresses = fn (array $items) => array_map(fn ($address) => ['emailAddress' => ['address' => $address->getAddress(), 'name' => $address->getName()]], $items);
        $payload = [
            'subject' => $email->getSubject(),
            'body' => ['contentType' => $email->getHtmlBody() !== null ? 'HTML' : 'Text', 'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? ''],
            'toRecipients' => $addresses($email->getTo()),
            'ccRecipients' => $addresses($email->getCc()),
            'bccRecipients' => $addresses($email->getBcc()),
            'replyTo' => $addresses($email->getReplyTo()),
        ];
        foreach ($email->getAttachments() as $attachment) {
            $body = $attachment->getBody();
            $payload['attachments'][] = [
                '@odata.type' => '#microsoft.graph.fileAttachment', 'name' => $attachment->getFilename() ?? 'attachment',
                'contentType' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'contentBytes' => base64_encode(is_resource($body) ? stream_get_contents($body) : $body),
            ];
        }
        $response = Http::withToken($token->json('access_token'))->timeout(30)->post(
            'https://graph.microsoft.com/v1.0/users/'.rawurlencode($this->settings['sender']).'/sendMail',
            ['message' => $payload, 'saveToSentItems' => true]
        );
        if ($response->status() !== 202) {
            throw new TransportException('Microsoft Graph mail submission failed (HTTP '.$response->status().'). Check mailbox permissions and Microsoft 365 message trace.');
        }
    }
}
