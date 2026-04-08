<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    private static int $itemId = 1000;

    public function definition(): array
    {
        return [
            'helloasso_item_id' => ++self::$itemId,
            'helloasso_order_id' => fake()->numberBetween(1, 9999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => (string) fake()->numberBetween(10000, 19999),
            'crem_photo_url' => null,
            'is_excluded' => false,
            'recovery_option' => null,
            'has_error' => false,
            'error_message' => null,
            'amphitheater_id' => null,
            'seat_number' => null,
            'is_manually_placed' => false,
            'is_manually_edited' => false,
            'is_present' => false,
            'marked_present_at' => null,
            'synced_at' => now(),
        ];
    }

    public function las1Member(): static
    {
        return $this->state([
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => (string) fake()->numberBetween(10000, 19999),
        ]);
    }

    public function las1MemberSansTuto(): static
    {
        return $this->state([
            'tier_name' => 'LAS 1 - INSCRITS AU CREM SANS le Tutorat',
            'crem_number' => (string) fake()->numberBetween(10000, 19999),
        ]);
    }

    public function las1NonMember(): static
    {
        return $this->state([
            'tier_name' => 'LAS 1 - NON INSCRITS au Tutorat',
            'crem_number' => null,
        ]);
    }

    public function las2Member(): static
    {
        return $this->state([
            'tier_name' => 'LAS 2/3 - INSCRITS au Tutorat',
            'crem_number' => (string) fake()->numberBetween(90000, 99999),
        ]);
    }

    public function las2MemberSansTuto(): static
    {
        return $this->state([
            'tier_name' => 'LAS 2/3 - INSCRITS AU CREM SANS le Tutorat',
            'crem_number' => (string) fake()->numberBetween(90000, 99999),
        ]);
    }

    public function las2NonMember(): static
    {
        return $this->state([
            'tier_name' => 'LAS 2/3 - NON INSCRITS au Tutorat',
            'crem_number' => null,
        ]);
    }

    public function excluded(): static
    {
        return $this->state([
            'is_excluded' => true,
            'tier_name' => "Récupération sans passer l'épreuve",
        ]);
    }

    public function withError(string $message = 'Erreur détectée'): static
    {
        return $this->state([
            'has_error' => true,
            'error_message' => $message,
        ]);
    }

    public function placed(int $amphiId, string $seat = '1'): static
    {
        return $this->state([
            'amphitheater_id' => $amphiId,
            'seat_number' => $seat,
        ]);
    }

    public function manuallyPlaced(int $amphiId, string $seat = '1'): static
    {
        return $this->state([
            'amphitheater_id' => $amphiId,
            'seat_number' => $seat,
            'is_manually_placed' => true,
        ]);
    }

    public function noCrem(): static
    {
        return $this->state(['crem_number' => null]);
    }

    public function withCrem(string $crem): static
    {
        return $this->state(['crem_number' => $crem]);
    }

    public function present(): static
    {
        return $this->state([
            'is_present' => true,
            'marked_present_at' => now(),
        ]);
    }
}
