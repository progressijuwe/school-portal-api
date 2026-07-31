<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Hashed once and reused — bcrypt is deliberately slow, and hashing per
     * user makes a suite that creates hundreds of them noticeably slower.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'department_id' => Department::factory(),
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /**
     * A student with a matric number and an entry year.
     *
     * The role itself is assigned in configure() — spatie roles are a relation,
     * not a column, so they cannot be set from the attribute array.
     */
    public function student(?int $entryYear = null): static
    {
        return $this->state(fn () => [
            'student_id' => 'CS/'.fake()->unique()->numerify('##U/######'),
            'study_type' => 'Undergraduate',
            'entry_year' => $entryYear ?? now()->year,
            'staff_id' => null,
        ])->afterCreating(fn (User $user) => $user->assignRole('student'));
    }

    public function lecturer(): static
    {
        return $this->state(fn () => [
            'staff_id' => 'SCI/CS/LEC/'.fake()->unique()->numerify('####/####'),
            'study_type' => null,
            'entry_year' => null,
            'student_id' => null,
        ])->afterCreating(function (User $user) {
            $user->assignRole('lecturer');
            $user->lecturerProfile()->create([
                'prefix' => 'Dr.',
                'highest_qualification' => 'PhD Computer Science',
                'specialization' => 'Software Engineering',
            ]);
        });
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'student_id' => null,
            'staff_id' => null,
            'study_type' => null,
            'entry_year' => null,
        ])->afterCreating(fn (User $user) => $user->assignRole('admin'));
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }
}
