<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Mail\ConversationReplyMail;
use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Tests\Fixtures\User;
use Illuminate\Support\Facades\Mail;

function postmarkPayload(array $overrides = []): array
{
    return array_merge([
        'FromFull' => ['Email' => 'customer@example.com', 'Name' => 'Casey Customer'],
        'Subject' => 'Help with my order',
        'TextBody' => 'Where is my order?',
        'MessageID' => 'msg-1',
        'Headers' => [],
    ], $overrides);
}

beforeEach(function () {
    config(['shared-inbox.channels.email.webhook_secret' => null]);
});

it('creates a contact and conversation from an inbound email webhook', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create([
        'config' => ['address' => 'support@acme.test'],
    ]);

    $response = $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", postmarkPayload());

    $response->assertNoContent();

    expect(Contact::count())->toBe(1);
    $contact = Contact::first();
    expect($contact->display_name)->toBe('Casey Customer')
        ->and($contact->channelIdentities()->where('external_id', 'customer@example.com')->exists())->toBeTrue();

    expect(Conversation::count())->toBe(1);
    $conversation = Conversation::first();
    expect($conversation->subject)->toBe('Help with my order')
        ->and($conversation->inbox_id)->toBe($inbox->id)
        ->and($conversation->messages()->count())->toBe(1);

    $message = $conversation->messages()->first();
    expect($message->direction)->toBe(Message::DIRECTION_INBOUND)
        ->and($message->body)->toBe('Where is my order?')
        ->and($message->external_id)->toBe('msg-1');
});

it('threads a reply into the existing conversation via In-Reply-To', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create();

    $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", postmarkPayload())
        ->assertNoContent();

    $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", postmarkPayload([
        'Subject' => 'Re: Help with my order',
        'TextBody' => 'Any update?',
        'MessageID' => 'msg-2',
        'Headers' => [['Name' => 'In-Reply-To', 'Value' => 'msg-1']],
    ]))->assertNoContent();

    expect(Conversation::count())->toBe(1)
        ->and(Contact::count())->toBe(1);

    $conversation = Conversation::first();
    expect($conversation->messages()->count())->toBe(2);
});

it('rejects an inbound webhook when the shared secret does not match', function () {
    config(['shared-inbox.channels.email.webhook_secret' => 'super-secret']);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create();

    $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", postmarkPayload())
        ->assertUnauthorized();

    expect(Conversation::count())->toBe(0);
});

it('sends an outbound reply and records it as a sent message', function () {
    Mail::fake();

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create(['config' => ['address' => 'support@acme.test']]);
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'email', 'external_id' => 'customer@example.com']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $user = User::create([
        'name' => 'Jordan Agent',
        'email' => 'jordan@acme.test',
        'password' => bcrypt('password'),
    ]);
    $team->users()->attach($user->id, ['role' => 'agent']);

    $response = $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/messages", [
            'body' => 'We are looking into it!',
        ]);

    $response->assertCreated();

    expect($conversation->messages()->count())->toBe(1);
    $message = $conversation->messages()->first();
    expect($message->direction)->toBe(Message::DIRECTION_OUTBOUND)
        ->and($message->status)->toBe(Message::STATUS_SENT)
        ->and($message->body)->toBe('We are looking into it!');

    Mail::assertSent(ConversationReplyMail::class, function (ConversationReplyMail $mail) {
        return $mail->hasTo('customer@example.com')
            && $mail->bodyText === 'We are looking into it!';
    });
});

it('parses a mailgun-format inbound webhook when configured', function () {
    config(['shared-inbox.channels.email.inbound_format' => 'mailgun']);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create();

    $response = $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", [
        'from' => 'Casey Customer <customer@example.com>',
        'subject' => 'Help with my order',
        'stripped-text' => 'Where is my order?',
        'Message-Id' => 'mg-msg-1',
    ]);

    $response->assertNoContent();

    $contact = Contact::first();
    expect($contact->display_name)->toBe('Casey Customer')
        ->and($contact->channelIdentities()->where('external_id', 'customer@example.com')->exists())->toBeTrue();

    $message = Conversation::first()->messages()->first();
    expect($message->body)->toBe('Where is my order?')
        ->and($message->external_id)->toBe('mg-msg-1');
});
