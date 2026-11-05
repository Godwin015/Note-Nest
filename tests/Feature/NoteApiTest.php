<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->actingAs($this->user);
    }

    public function test_it_creates_a_note_with_default_pin_state(): void
    {
        $response = $this->postJson('/api/notes', [
            'title' => 'Team Meeting',
            'content' => 'Discuss the project progress.',
            'category' => 'work',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Team Meeting')
            ->assertJsonPath('data.category', 'work')
            ->assertJsonPath('data.is_pinned', false);

        $this->assertDatabaseHas('notes', [
            'user_id' => $this->user->id,
            'title' => 'Team Meeting',
            'content' => 'Discuss the project progress.',
            'is_pinned' => 0,
        ]);
    }

    public function test_it_rejects_missing_fields_and_overlong_content(): void
    {
        $this->postJson('/api/notes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);

        $this->postJson('/api/notes', [
            'title' => 'Valid title',
            'content' => str_repeat('a', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_it_updates_a_note(): void
    {
        $note = $this->user->notes()->create([
            'title' => 'Old title',
            'content' => 'Old content',
        ]);

        $this->patchJson("/api/notes/{$note->id}", [
            'title' => 'Updated title',
            'content' => 'Updated content',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.content', 'Updated content');

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'user_id' => $this->user->id,
            'title' => 'Updated title',
        ]);
    }

    public function test_it_deletes_a_note(): void
    {
        $note = $this->user->notes()->create([
            'title' => 'Delete me',
            'content' => 'This note will be deleted.',
        ]);

        $this->deleteJson("/api/notes/{$note->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('notes', [
            'id' => $note->id,
        ]);
    }

    public function test_search_and_category_filter_work_together(): void
    {
        $matching = $this->user->notes()->create([
            'title' => 'Project planning',
            'content' => 'Prepare the agenda.',
            'category' => 'work',
        ]);

        $this->user->notes()->create([
            'title' => 'Personal reminder',
            'content' => 'Remember the project deadline.',
            'category' => 'personal',
        ]);

        $this->user->notes()->create([
            'title' => 'Shopping list',
            'content' => 'Buy groceries.',
            'category' => 'work',
        ]);

        $this->getJson('/api/notes?search=PROJECT&category=work')
            ->assertOk()
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_pin_status_changes_and_pinned_notes_appear_first(): void
    {
        $ordinary = $this->user->notes()->create([
            'title' => 'Ordinary note',
            'content' => 'Regular content.',
        ]);

        $important = $this->user->notes()->create([
            'title' => 'Important note',
            'content' => 'Important content.',
        ]);

        $this->patchJson("/api/notes/{$important->id}/pin", [
            'is_pinned' => true,
        ])->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->getJson('/api/notes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $important->id)
            ->assertJsonPath('data.1.id', $ordinary->id);

        $this->patchJson("/api/notes/{$important->id}/pin", [
            'is_pinned' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_pinned', false);
    }

    public function test_missing_note_returns_404(): void
    {
        $this->getJson('/api/notes/999999')
            ->assertNotFound();

        $this->patchJson('/api/notes/999999', [
            'title' => 'Updated title',
        ])->assertNotFound();

        $this->deleteJson('/api/notes/999999')
            ->assertNotFound();
    }

    public function test_notes_are_paginated_ten_per_page(): void
    {
        Note::factory()
            ->count(11)
            ->for($this->user)
            ->create();

        $this->getJson('/api/notes?page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('last_page', 2);

        $this->getJson('/api/notes?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_access_another_users_note(): void
    {
        $anotherUser = User::factory()->create();

        $note = $anotherUser->notes()->create([
            'title' => 'Private note',
            'content' => 'This belongs to another user.',
        ]);

        // User cannot view another user's note.
        $this->getJson("/api/notes/{$note->id}")
            ->assertNotFound();

        // User cannot update another user's note.
        $this->patchJson("/api/notes/{$note->id}", [
            'title' => 'Trying to update',
            'content' => 'Unauthorized modification.',
        ])->assertNotFound();

        // Verify the original note remains unchanged.
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'user_id' => $anotherUser->id,
            'title' => 'Private note',
            'content' => 'This belongs to another user.',
        ]);

        // User cannot delete another user's note.
        $this->deleteJson("/api/notes/{$note->id}")
            ->assertNotFound();

        // Verify the note still exists.
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'user_id' => $anotherUser->id,
        ]);
    }


    public function test_it_supports_custom_page_sizes(): void
    {
        Note::factory()
            ->count(25)
            ->for($this->user)
            ->create();

        $this->getJson('/api/notes?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('last_page', 2);

        $this->getJson('/api/notes?per_page=50')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('per_page', 50);
    }

    public function test_it_rejects_invalid_page_sizes(): void
    {
        $this->getJson('/api/notes?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/notes?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_search_works_with_pagination(): void
    {
        Note::factory()
            ->count(15)
            ->for($this->user)
            ->create([
                'title' => 'Laravel Development',
                'content' => 'Learning backend development.',
            ]);

        $this->getJson('/api/notes?search=Laravel&per_page=10&page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15)
            ->assertJsonPath('last_page', 2);

        $this->getJson('/api/notes?search=Laravel&per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('total', 15);
    }

    public function test_it_filters_notes_by_creation_date(): void
    {
        $older = $this->user->notes()->create([
            'title' => 'Older note',
            'content' => 'Created earlier.',
        ]);

        \Illuminate\Support\Facades\DB::table('notes')
            ->where('id', $older->id)
            ->update([
                'created_at' => '2026-10-20 10:00:00',
                'updated_at' => '2026-10-20 10:00:00',
            ]);

        $newer = $this->user->notes()->create([
            'title' => 'Newer note',
            'content' => 'Created later.',
        ]);

        \Illuminate\Support\Facades\DB::table('notes')
            ->where('id', $newer->id)
            ->update([
                'created_at' => '2026-11-03 10:00:00',
                'updated_at' => '2026-11-03 10:00:00',
            ]);

        $this->getJson('/api/notes?created_from=2026-11-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newer->id);

        $this->getJson('/api/notes?created_to=2026-10-25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $older->id);
    }

    public function test_it_sorts_notes_by_title(): void
    {
        $this->user->notes()->create([
            'title' => 'Zebra',
            'content' => 'Last alphabetically.',
        ]);

        $this->user->notes()->create([
            'title' => 'Apple',
            'content' => 'First alphabetically.',
        ]);

        $this->getJson('/api/notes?sort_by=title&sort_direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Apple')
            ->assertJsonPath('data.1.title', 'Zebra');
    }

    public function test_it_sorts_notes_by_creation_date(): void
    {
        $older = $this->user->notes()->create([
            'title' => 'Older note',
            'content' => 'Older content.',
        ]);

        \Illuminate\Support\Facades\DB::table('notes')
            ->where('id', $older->id)
            ->update([
                'created_at' => '2026-10-20 10:00:00',
                'updated_at' => '2026-10-20 10:00:00',
            ]);

        $newer = $this->user->notes()->create([
            'title' => 'Newer note',
            'content' => 'Newer content.',
        ]);

        \Illuminate\Support\Facades\DB::table('notes')
            ->where('id', $newer->id)
            ->update([
                'created_at' => '2026-11-03 10:00:00',
                'updated_at' => '2026-11-03 10:00:00',
            ]);

        $this->getJson('/api/notes?sort_by=created_at&sort_direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_it_sorts_notes_by_update_date(): void
    {
        $older = $this->user->notes()->create([
            'title' => 'Older update',
            'content' => 'Older content.',
        ]);

        \Illuminate\Support\Facades\DB::table('notes')
            ->where('id', $older->id)
            ->update([
                'created_at' => '2026-10-20 10:00:00',
                'updated_at' => '2026-10-20 10:00:00',
            ]);

        $newer = $this->user->notes()->create([
            'title' => 'Newer update',
            'content' => 'Newer content.',
        ]);

        \Illuminate\Support\Facades\DB::table('notes')
            ->where('id', $newer->id)
            ->update([
                'created_at' => '2026-10-20 10:00:00',
                'updated_at' => '2026-11-03 10:00:00',
            ]);

        $this->getJson('/api/notes?sort_by=updated_at&sort_direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_it_rejects_invalid_sorting_parameters(): void
    {
        $this->getJson('/api/notes?sort_by=user_id')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_by']);

        $this->getJson('/api/notes?sort_direction=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_direction']);
    }

    public function test_search_never_returns_another_users_notes(): void
    {
        $this->user->notes()->create([
            'title' => 'Laravel private note',
            'content' => 'My own content.',
        ]);

        $anotherUser = User::factory()->create();

        $anotherUser->notes()->create([
            'title' => 'Laravel confidential note',
            'content' => 'Another users content.',
        ]);

        $this->getJson('/api/notes?search=Laravel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel private note');
    }

}
