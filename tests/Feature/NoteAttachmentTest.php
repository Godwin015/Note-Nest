<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoteAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Note $note;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();

        $this->actingAs($this->user);

        $this->note = $this->user->notes()->create([
            'title' => 'Attachment test note',
            'content' => 'Testing note attachments.',
        ]);
    }

    /**
     * Upload an attachment to a note.
     */
    public function test_it_uploads_an_attachment_to_a_note(): void
    {
        $file = UploadedFile::fake()->create(
            'document.pdf',
            100,
            'application/pdf'
        );

        $response = $this->postJson(
            "/api/notes/{$this->note->id}/attachments",
            [
                'file' => $file,
            ]
        );

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Attachment uploaded successfully.'
            )
            ->assertJsonPath(
                'data.original_name',
                'document.pdf'
            );

        $this->assertDatabaseHas('note_attachments', [
            'note_id' => $this->note->id,
            'original_name' => 'document.pdf',
        ]);

        $attachment = NoteAttachment::where(
            'note_id',
            $this->note->id
        )->firstOrFail();

        Storage::disk('public')->assertExists(
            $attachment->file_path
        );
    }

    /**
     * List attachments belonging to a note.
     */
    public function test_it_lists_attachments_belonging_to_a_note(): void
    {
        $this->createAttachment('document.pdf');

        $this->createAttachment('report.txt');

        $response = $this->getJson(
            "/api/notes/{$this->note->id}/attachments"
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Delete an attachment and its stored file.
     */
    public function test_it_deletes_an_attachment_and_its_file(): void
    {
        $attachment = $this->createAttachment(
            'document.pdf'
        );

        $filePath = $attachment->file_path;

        Storage::disk('public')->assertExists($filePath);

        $this->deleteJson(
            "/api/notes/{$this->note->id}/attachments/{$attachment->id}"
        )->assertOk()
            ->assertJsonPath(
                'message',
                'Attachment deleted successfully.'
            );

        $this->assertDatabaseMissing('note_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertMissing($filePath);
    }

    /**
     * Download an attachment belonging to a note.
     */
    public function test_it_downloads_an_attachment(): void
    {
        $attachment = $this->createAttachment(
            'document.pdf'
        );

        Storage::disk('public')->put(
            $attachment->file_path,
            'Test PDF content'
        );

        $response = $this->get(
            "/api/notes/{$this->note->id}/attachments/{$attachment->id}/download"
        );

        $response->assertOk()
            ->assertHeader(
                'content-disposition',
                'attachment; filename=document.pdf'
            );
    }

    /**
     * Preview an image attachment belonging to a note.
     */
    public function test_it_previews_an_image_attachment(): void
    {
        $attachment = $this->createAttachment(
            'image.png',
            'image/png'
        );

        Storage::disk('public')->put(
            $attachment->file_path,
            'Fake image content'
        );

        $response = $this->get(
            "/api/notes/{$this->note->id}/attachments/{$attachment->id}/preview"
        );

        $response->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader(
                'content-disposition',
                'inline'
            );
    }

    /**
     * Reject invalid attachment file types.
     */
    public function test_it_rejects_invalid_attachment_file_types(): void
    {
        $file = UploadedFile::fake()->create(
            'script.php',
            100,
            'application/x-php'
        );

        $this->postJson(
            "/api/notes/{$this->note->id}/attachments",
            [
                'file' => $file,
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('note_attachments', 0);
    }

    /**
     * Enforce the maximum of five attachments per note.
     */
    public function test_it_rejects_more_than_five_attachments(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createAttachment("document{$i}.pdf");
        }

        $file = UploadedFile::fake()->create(
            'sixth-document.pdf',
            100,
            'application/pdf'
        );

        $this->postJson(
            "/api/notes/{$this->note->id}/attachments",
            [
                'file' => $file,
            ]
        )->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A note may have a maximum of 5 attachments.'
            );

        $this->assertDatabaseCount('note_attachments', 5);
    }

    /**
     * Prevent access to another user's attachments.
     */
    public function test_user_cannot_access_another_users_attachments(): void
    {
        $anotherUser = User::factory()->create();

        $anotherNote = $anotherUser->notes()->create([
            'title' => 'Another user note',
            'content' => 'Private note content.',
        ]);

        $attachment = $anotherNote->attachments()->create([
            'original_name' => 'private.pdf',
            'file_path' => 'note-attachments/' . $anotherNote->id . '/private.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);

        Storage::disk('public')->put(
            $attachment->file_path,
            'Private attachment content'
        );

        // Upload to another user's note.
        $file = UploadedFile::fake()->create(
            'unauthorized.pdf',
            100,
            'application/pdf'
        );

        $this->postJson(
            "/api/notes/{$anotherNote->id}/attachments",
            [
                'file' => $file,
            ]
        )->assertNotFound();

        // List another user's attachments.
        $this->getJson(
            "/api/notes/{$anotherNote->id}/attachments"
        )->assertNotFound();

        // Delete another user's attachment.
        $this->deleteJson(
            "/api/notes/{$anotherNote->id}/attachments/{$attachment->id}"
        )->assertNotFound();

        // Download another user's attachment.
        $this->get(
            "/api/notes/{$anotherNote->id}/attachments/{$attachment->id}/download"
        )->assertNotFound();

        // Preview another user's attachment.
        $this->get(
            "/api/notes/{$anotherNote->id}/attachments/{$attachment->id}/preview"
        )->assertNotFound();

        // Verify the other user's attachment remains intact.
        $this->assertDatabaseHas('note_attachments', [
            'id' => $attachment->id,
            'note_id' => $anotherNote->id,
        ]);

        Storage::disk('public')->assertExists(
            $attachment->file_path
        );
    }

    /**
     * Create an attachment record for the current user's note.
     */
    private function createAttachment(
        string $filename,
        string $mimeType = 'application/pdf'
    ): NoteAttachment {
        $path = 'note-attachments/' . $this->note->id . '/' . $filename;

        Storage::disk('public')->put(
            $path,
            'Test attachment content'
        );

        return $this->note->attachments()->create([
            'original_name' => $filename,
            'file_path' => $path,
            'mime_type' => $mimeType,
            'file_size' => 100,
        ]);
    }
}
