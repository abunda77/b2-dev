<?php

namespace Tests\Feature;

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Models\ConversationMessage;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        ChatAgent::fake(['Hello! I am an AI assistant.']);
    }

    public function test_chat_page_loads_for_authenticated_user(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->assertOk()
            ->assertSee('Chat AI');
    }

    public function test_chat_page_shows_provider_and_model_selectors(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test('pages::chat.index');

        $this->assertNotEmpty($component->get('selectedProvider'));
        $this->assertNotEmpty($component->get('selectedModel'));
    }

    public function test_send_message_creates_conversation_and_persists(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Hello, AI!')
            ->call('send')
            ->assertSet('isGenerating', false)
            ->assertSet('activeConversationId', fn ($id) => filled($id));

        $this->assertDatabaseHas('agent_conversations', [
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'user',
            'content' => 'Hello, AI!',
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'assistant',
        ]);
    }

    public function test_send_message_shows_response_in_chat(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Hello')
            ->call('send')
            ->assertSee('Hello! I am an AI assistant.');
    }

    public function test_continue_conversation_adds_messages(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'First message')
            ->call('send');

        $conversationId = $component->get('activeConversationId');

        $component
            ->set('message', 'Second message')
            ->call('send')
            ->assertSet('activeConversationId', $conversationId);

        $this->assertEquals(4, ConversationMessage::where('conversation_id', $conversationId)->count());
    }

    public function test_new_conversation_resets_state(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Hello')
            ->call('send');

        $component
            ->call('newConversation')
            ->assertSet('activeConversationId', null)
            ->assertSet('chatMessages', [])
            ->assertSet('message', '');
    }

    public function test_select_conversation_loads_messages(): void
    {
        ChatAgent::fake(['AI response']);

        $component = Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Test message')
            ->call('send');

        $conversationId = $component->get('activeConversationId');

        $component
            ->call('newConversation')
            ->assertSet('chatMessages', []);

        $component
            ->call('selectConversation', $conversationId)
            ->assertCount('chatMessages', 2);
    }

    public function test_delete_conversation_removes_from_db(): void
    {
        ChatAgent::fake(['Response']);

        $component = Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Test')
            ->call('send');

        $conversationId = $component->get('activeConversationId');

        $component
            ->call('deleteConversation', $conversationId);

        $this->assertDatabaseMissing('agent_conversations', ['id' => $conversationId]);
        $this->assertDatabaseMissing('agent_conversation_messages', ['conversation_id' => $conversationId]);
    }

    public function test_validation_requires_message(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', '')
            ->call('send')
            ->assertHasErrors(['message']);
    }

    public function test_provider_change_updates_model_default(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('selectedProvider', 'openai')
            ->set('selectedProvider', 'deepseek')
            ->assertSet('selectedModel', 'deepseek-v4-flash');
    }

    public function test_send_with_attachments_passes_to_agent(): void
    {
        ChatAgent::fake(['Got your file!']);

        $image = UploadedFile::fake()->image('photo.jpg', 100, 100);

        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Analyze this image')
            ->set('attachments', [$image])
            ->call('send')
            ->assertSee('Got your file!');

        ChatAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Analyze this image'));
    }

    public function test_image_warning_shown_for_non_vision_model(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('selectedProvider', 'deepseek')
            ->set('selectedModel', 'deepseek-v4-flash')
            ->set('attachments', [UploadedFile::fake()->image('photo.jpg', 100, 100)])
            ->assertSee('tidak mendukung gambar');
    }

    public function test_image_warning_not_shown_for_vision_model(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('selectedProvider', 'openai')
            ->set('selectedModel', 'gpt-4o')
            ->set('attachments', [UploadedFile::fake()->image('photo.jpg', 100, 100)])
            ->assertDontSee('tidak mendukung gambar');
    }

    public function test_delete_button_exists_on_conversation_item(): void
    {
        ChatAgent::fake(['Hi']);

        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Hello')
            ->call('send')
            ->assertSeeHtml('btn-delete-conv');
    }

    public function test_user_model_has_conversations_relation(): void
    {
        $this->assertTrue(method_exists(User::class, 'conversations'));

        ChatAgent::fake(['Hi']);

        Livewire::actingAs($this->user)
            ->test('pages::chat.index')
            ->set('message', 'Hi')
            ->call('send');

        $this->assertCount(1, $this->user->conversations);
    }
}
