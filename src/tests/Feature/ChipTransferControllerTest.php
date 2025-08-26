<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserChipBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChipTransferControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->user1 = User::factory()->create(['id' => 1]);
        $this->user2 = User::factory()->create(['id' => 2]);
        
        // Give user1 some chips for transfers
        UserChipBalance::create([
            'user_id' => 1,
            'balance' => 5000,
            'last_updated_at' => now()
        ]);
    }

    public function test_successful_chip_transfer(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 100
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Chip transfer completed successfully',
                    'data' => [
                        'from_user_id' => 1,
                        'to_user_id' => 2,
                        'amount' => 100
                    ]
                ])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'from_user_id',
                        'to_user_id',
                        'amount',
                        'transaction_id',
                        'from_balance',
                        'to_balance'
                    ]
                ]);
    }

    public function test_validation_requires_from_player_id(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'toPlayerId' => 2,
            'amount' => 100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['fromPlayerId']);
    }

    public function test_validation_requires_to_player_id(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'amount' => 100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['toPlayerId']);
    }

    public function test_validation_requires_amount(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    public function test_validation_from_player_id_must_be_integer(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 'invalid',
            'toPlayerId' => 2,
            'amount' => 100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['fromPlayerId']);
    }

    public function test_validation_to_player_id_must_be_integer(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 'invalid',
            'amount' => 100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['toPlayerId']);
    }

    public function test_validation_amount_must_be_integer(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 'invalid'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    public function test_validation_amount_must_be_positive(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 0
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    public function test_validation_amount_cannot_be_negative(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => -100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    public function test_validation_from_player_must_exist(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 999,
            'toPlayerId' => 2,
            'amount' => 100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['fromPlayerId']);
    }

    public function test_validation_to_player_must_exist(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 999,
            'amount' => 100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['toPlayerId']);
    }

    public function test_validation_cannot_transfer_to_same_player(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 1,
            'amount' => 100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['toPlayerId']);
    }

    public function test_validation_amount_cannot_exceed_maximum(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 5001
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    public function test_transaction_id_is_unique_on_each_request(): void
    {
        $response1 = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 100
        ]);

        $response2 = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 50
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $transactionId1 = $response1->json('data.transaction_id');
        $transactionId2 = $response2->json('data.transaction_id');

        $this->assertNotEquals($transactionId1, $transactionId2);
    }

    public function test_transaction_id_starts_with_chip_transfer_prefix(): void
    {
        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 100
        ]);

        $response->assertStatus(200);
        $transactionId = $response->json('data.transaction_id');
        
        $this->assertStringStartsWith('chip_transfer_', $transactionId);
    }

    public function test_insufficient_balance_returns_error(): void
    {
        // First reduce user1's balance
        UserChipBalance::where('user_id', 1)->update(['balance' => 50]);

        $response = $this->postJson('/api/transfer-chips', [
            'fromPlayerId' => 1,
            'toPlayerId' => 2,
            'amount' => 100 // More than user1's updated balance of 50
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Insufficient chip balance',
                    'data' => [
                        'current_balance' => 50,
                        'requested_amount' => 100
                    ]
                ]);
    }
}