<?php

namespace Database\Factories;

use App\Models\DocumentSignoff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSignoff>
 */
class DocumentSignoffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => 'incoming_receipt',
            'document_id' => 1,
            'step' => 'received_and_processed',
            'role_required' => 'Supply Chain Officer',
            'user_id' => User::factory(),
            'signer_name' => 'TST '.strtoupper(now('Asia/Jakarta')->format('d M Y')),
            'signed_at' => now('Asia/Jakarta'),
        ];
    }
}
