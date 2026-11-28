<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('OWNER_RECOVERY_TOKEN');
        unset($_ENV['OWNER_RECOVERY_TOKEN'], $_SERVER['OWNER_RECOVERY_TOKEN']);
        parent::tearDown();
    }

    public function test_invalid_recovery_token_is_not_found(): void
    {
        putenv('OWNER_RECOVERY_TOKEN=recovery-token-that-is-at-least-32-characters');
        $_ENV['OWNER_RECOVERY_TOKEN'] = 'recovery-token-that-is-at-least-32-characters';

        $this->get('/__owner-recovery/not-the-configured-recovery-token')
            ->assertNotFound();
    }

    public function test_recovery_route_provisions_or_recovers_the_designated_owner_once(): void
    {
        $token = 'owner-recovery-token-that-is-at-least-32-characters';
        putenv('OWNER_RECOVERY_TOKEN='.$token);
        $_ENV['OWNER_RECOVERY_TOKEN'] = $token;
        Cache::flush();

        $this->get('/__owner-recovery/'.$token)->assertOk();

        $this->post('/__owner-recovery/'.$token, [
            'password' => 'TemporaryOwner123!',
            'password_confirmation' => 'TemporaryOwner123!',
        ])->assertRedirect('/login');

        $owner = User::query()->where('email', 'fivecegas8@gmail.com')->firstOrFail();

        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertNull($owner->branch_id);
        $this->assertTrue($owner->is_active);
        $this->assertTrue(Hash::check('TemporaryOwner123!', $owner->password));

        $this->get('/__owner-recovery/'.$token)->assertNotFound();
    }
}
