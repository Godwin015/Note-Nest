<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteApiTest extends TestCase
{
    use RefreshDatabase;

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
        $note = Note::create([
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
            'title' => 'Updated title',
        ]);
    }

    public function test_it_deletes_a_note(): void
    {
        $note = Note::create([
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
        $matching = Note::create([
            'title' => 'Project planning',
            'content' => 'Prepare the agenda.',
            'category' => 'work',
        ]);

        Note::create([
            'title' => 'Personal reminder',
            'content' => 'Remember the project deadline.',
            'category' => 'personal',
        ]);

        Note::create([
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
        $ordinary = Note::create([
            'title' => 'Ordinary note',
            'content' => 'Regular content.',
        ]);

        $important = Note::create([
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
        $this->getJson('/api/notes/999999')->assertNotFound();
        $this->patchJson('/api/notes/999999', [
            'title' => 'Updated title',
        ])->assertNotFound();
        $this->deleteJson('/api/notes/999999')->assertNotFound();
    }

    public function test_notes_are_paginated_ten_per_page(): void
    {
        Note::factory()->count(11)->create();

        $this->getJson('/api/notes?page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('last_page', 2);

        $this->getJson('/api/notes?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
