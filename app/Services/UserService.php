<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(
        private readonly IdGeneratorService $idGenerator,
        private readonly CloudinaryService $cloudinary,
    ) {}

    /**
     * Create a student or lecturer account.
     *
     * The user row, role assignment and lecturer profile are one atomic unit —
     * previously only the bulk-import caller wrapped this in a transaction, so
     * a failure on the single-create path could leave a user with no role or no
     * profile. The welcome notification is dispatched after commit so a mail
     * failure can no longer roll back a successfully created account.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, password: string}
     */
    public function createUser(array $data, ?UploadedFile $photo = null): array
    {
        $temporaryPassword = $this->generateTemporaryPassword();
        $role = $data['role'];

        // Deliberately outside the transaction: this is a network call to
        // Cloudinary, and holding a database transaction open across it would
        // pin a connection for the duration of the upload.
        $photoData = $photo !== null
            ? $this->uploadPhoto($photo)
            : [];

        $user = DB::transaction(function () use ($data, $role, $temporaryPassword, $photoData) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'department_id' => $data['department_id'],
                'study_type' => $role === 'student' ? $data['study_type'] : null,
                'entry_year' => $role === 'student' ? $data['entry_year'] : null,
                'must_change_password' => true,
                'student_id' => $role === 'student'
                    ? $this->idGenerator->generateStudentId(
                        (int) $data['department_id'],
                        $data['study_type'],
                        (int) $data['entry_year'],
                    )
                    : null,
                'staff_id' => $role === 'lecturer'
                    ? $this->idGenerator->generateStaffId((int) $data['department_id'])
                    : null,
                ...$photoData,
            ]);

            $user->assignRole($role);

            if ($role === 'lecturer') {
                $user->lecturerProfile()->create([
                    'prefix' => $data['prefix'],
                    'highest_qualification' => $data['highest_qualification'],
                    'specialization' => $data['specialization'] ?? null,
                ]);
            }

            return $user;
        });

        $user->notify(new UserRegisteredNotification($temporaryPassword, $role));
        $user->load('department.faculty', 'lecturerProfile');

        return [
            'user' => $user,
            'password' => $temporaryPassword,
        ];
    }

    /**
     * Issue a fresh temporary password for a user who has lost theirs.
     *
     * Returns the plain password so the caller can show it to the administrator
     * performing the reset. With no mail service configured there is nowhere
     * else for it to go, and a reset the admin cannot read out is a reset that
     * locks the account rather than recovering it.
     *
     * Existing tokens are revoked so any session still holding the old
     * credentials is ended immediately, and `must_change_password` is set so
     * the temporary password cannot become the permanent one.
     */
    public function resetPassword(User $user): string
    {
        $temporaryPassword = $this->generateTemporaryPassword();

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'password_reset_requested_at' => null,
        ]);

        $user->tokens()->delete();

        return $temporaryPassword;
    }

    /**
     * @return array{profile_photo_url: string, profile_photo_public_id: string}
     */
    private function uploadPhoto(UploadedFile $photo): array
    {
        $uploaded = $this->cloudinary->uploadProfilePhoto($photo);

        return [
            'profile_photo_url' => $uploaded['url'],
            'profile_photo_public_id' => $uploaded['public_id'],
        ];
    }

    /**
     * Readable one-time password, e.g. "Xk9q42!Abc". The account is created
     * with must_change_password set, so this only has to survive one login.
     */
    private function generateTemporaryPassword(): string
    {
        return ucfirst(Str::random(4)).random_int(10, 99).'!'.Str::random(3);
    }
}
