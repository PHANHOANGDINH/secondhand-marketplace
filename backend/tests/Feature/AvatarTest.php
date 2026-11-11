<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/account/profile']);
    }

    private function signIn(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user, 'web');

        return $user;
    }

    private function picture(int $width = 800, int $height = 600, string $name = 'me.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    private function upload(UploadedFile $file)
    {
        return $this->post('/api/v1/profile/avatar', ['avatar' => $file], ['Accept' => 'application/json']);
    }

    public function test_uploading_redraws_a_square_jpeg_and_returns_its_url(): void
    {
        $user = $this->signIn();

        $response = $this->upload($this->picture(800, 600))->assertOk();

        $path = $user->refresh()->avatar_path;
        $this->assertStringStartsWith("avatars/{$user->id}/", $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, $response->json('data.avatar_url'));
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([512, 512, IMAGETYPE_JPEG], [$width, $height, $type]);
    }

    public function test_the_me_endpoint_carries_the_picture(): void
    {
        $user = $this->signIn();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.avatar_url', null);

        $this->upload($this->picture())->assertOk();

        $this->assertStringContainsString($user->refresh()->avatar_path, $this->getJson('/api/v1/auth/me')->json('data.avatar_url'));
    }

    public function test_a_new_picture_replaces_and_deletes_the_old_file(): void
    {
        $user = $this->signIn();
        $this->upload($this->picture())->assertOk();
        $first = $user->refresh()->avatar_path;

        $this->upload($this->picture(300, 300, 'next.png'))->assertOk();

        $this->assertNotSame($first, $user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_removing_clears_the_picture_and_the_file(): void
    {
        $user = $this->signIn();
        $this->upload($this->picture())->assertOk();
        $path = $user->refresh()->avatar_path;

        $this->deleteJson('/api/v1/profile/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
        // Removing again is harmless.
        $this->deleteJson('/api/v1/profile/avatar')->assertOk();
    }

    public function test_metadata_in_the_upload_does_not_reach_the_public_file(): void
    {
        $user = $this->signIn();
        $file = UploadedFile::fake()->createWithContent('me.jpg', $this->jpegWithComment('GPS-SECRET-MARKER'));

        $this->upload($file)->assertOk();

        $this->assertStringNotContainsString('GPS-SECRET-MARKER', Storage::disk('public')->get($user->refresh()->avatar_path));
    }

    public function test_files_that_are_not_pictures_are_refused(): void
    {
        $user = $this->signIn();

        $this->upload(UploadedFile::fake()->create('virus.php', 10, 'application/x-php'))->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->upload(UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;'))->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->upload(UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg'))->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->post('/api/v1/profile/avatar', [], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertNull($user->refresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_only_the_signed_in_active_user_changes_their_own_picture(): void
    {
        $this->post('/api/v1/profile/avatar', ['avatar' => $this->picture()], ['Accept' => 'application/json'])->assertUnauthorized();

        $this->signIn(['status' => UserStatus::SUSPENDED]);
        $this->upload($this->picture())->assertForbidden();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_path_cannot_be_set_through_the_profile_form(): void
    {
        $user = $this->signIn();

        $this->patchJson('/api/v1/profile', ['name' => 'Tên Mới', 'avatar_path' => '../../private'])->assertUnprocessable();

        $this->assertNull($user->refresh()->avatar_path);
    }

    /** A real JPEG with a text comment segment inserted right after the start marker. */
    private function jpegWithComment(string $text): string
    {
        $image = imagecreatetruecolor(50, 50);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);
        $segment = "\xFF\xFE".pack('n', strlen($text) + 2).$text;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
