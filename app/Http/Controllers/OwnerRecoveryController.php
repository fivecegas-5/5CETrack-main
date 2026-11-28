<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OwnerRecoveryController extends Controller
{
    private const OWNER_NAME = 'Clervy Ann M. Esteves';

    private const OWNER_EMAIL = 'fivecegas8@gmail.com';

    public function show(Request $request, string $token): View
    {
        $this->authorizeToken($token);

        return view('auth.owner-recovery');
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->authorizeToken($token);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $owner = User::query()->where('email', self::OWNER_EMAIL)->first();

        if ($owner === null) {
            app(BranchSeeder::class)->run();

            if (User::query()
                ->where('role', UserRole::Owner->value)
                ->whereNull('branch_id')
                ->where('is_active', true)
                ->exists()) {
                abort(409, 'A different active global owner already exists.');
            }

            $owner = User::create([
                'name' => self::OWNER_NAME,
                'email' => self::OWNER_EMAIL,
                'password' => Hash::make($data['password']),
                'role' => UserRole::Owner,
                'branch_id' => null,
                'is_active' => true,
            ]);
        } else {
            $owner->forceFill([
                'name' => self::OWNER_NAME,
                'password' => Hash::make($data['password']),
                'role' => UserRole::Owner,
                'branch_id' => null,
                'is_active' => true,
                'remember_token' => Str::random(60),
            ])->save();
        }

        Cache::forever($this->usedCacheKey($token), true);

        return redirect()->route('login')->with(
            'status',
            'Owner recovery completed. Sign in with the password you entered.',
        );
    }

    private function authorizeToken(string $token): void
    {
        $configured = env('OWNER_RECOVERY_TOKEN');

        if (! is_string($configured)
            || strlen($configured) < 32
            || ! hash_equals($configured, $token)
            || Cache::get($this->usedCacheKey($token)) === true) {
            abort(404);
        }
    }

    private function usedCacheKey(string $token): string
    {
        return 'owner-recovery-used:'.hash('sha256', $token);
    }
}
