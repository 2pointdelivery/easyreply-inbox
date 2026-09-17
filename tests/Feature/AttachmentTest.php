<?php

use Easyreply\Inbox\Models\Attachment;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an uploaded attachment on an outbound message', function () {
    Storage::fake('local');

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'email', 'external_id' => 'customer@example.com']);
    $conversation = conversationForTeam($team);
    $conversation->update(['inbox_id' => $inbox->id, 'contact_id' => $contact->id]);

    $response = $this->actingAs($user)->post("/shared-inbox/conversations/{$conversation->id}/messages", [
        'body' => 'See attached invoice.',
        'attachments' => [UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf')],
    ]);

    $response->assertCreated();

    expect(Attachment::count())->toBe(1);
    $attachment = Attachment::first();
    expect($attachment->filename)->toBe('invoice.pdf');
    Storage::disk('local')->assertExists($attachment->path);
});
