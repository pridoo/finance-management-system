<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Profile pictures uploaded from User Management are saved as
 * public/images/avatars/{email name}.{ext}, follow the account when its email
 * changes, and are removed with the account.
 */
class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(public_path(User::AVATAR_DIR.'/avatartest*')) as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    /** A real 1x1 PNG, so the "image" validation rule accepts it. */
    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Avatar', 'last_name' => 'Test',
            'email' => 'avatartest@sanagustin.gov.ph',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'mswdo_staff', 'is_active' => 1, 'permissions' => ['dashboard'],
        ], $overrides);
    }

    public function test_picture_is_saved_renamed_removed_and_deleted_with_the_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create with a picture.
        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->form(['avatar' => $this->png()]))
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'avatartest@sanagustin.gov.ph')->firstOrFail();
        $this->assertFileExists(public_path(User::AVATAR_DIR.'/avatartest.png'));
        $this->assertNotNull($user->avatar_url);

        // Change the email: the picture follows the new name.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), $this->form([
                'email' => 'avatartest2@sanagustin.gov.ph', 'password' => '', 'password_confirmation' => '',
            ]))
            ->assertRedirect(route('admin.users.index'));

        $this->assertFileDoesNotExist(public_path(User::AVATAR_DIR.'/avatartest.png'));
        $this->assertFileExists(public_path(User::AVATAR_DIR.'/avatartest2.png'));

        // Remove it.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), $this->form([
                'email' => 'avatartest2@sanagustin.gov.ph', 'password' => '', 'password_confirmation' => '',
                'remove_avatar' => 1,
            ]));

        $this->assertNull($user->fresh()->avatar_url);

        // Upload again, then delete the account: the picture goes too.
        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), $this->form([
                'email' => 'avatartest2@sanagustin.gov.ph', 'password' => '', 'password_confirmation' => '',
                'avatar' => $this->png(),
            ]))->assertRedirect(route('admin.users.index'));
        $this->assertFileExists(public_path(User::AVATAR_DIR.'/avatartest2.png'));

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user));
        $this->assertFileDoesNotExist(public_path(User::AVATAR_DIR.'/avatartest2.png'));
    }

    public function test_a_non_image_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->form([
                'avatar' => UploadedFile::fake()->createWithContent('notes.txt', 'hello'),
            ]))
            ->assertSessionHasErrors('avatar');
    }
}
