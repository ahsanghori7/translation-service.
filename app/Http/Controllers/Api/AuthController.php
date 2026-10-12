<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\TokenManager;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly TokenManager $tokens)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User();
        $user->forceFill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'role' => UserRole::Viewer,
        ])->save();

        $issued = $this->tokens->issue(
            $user,
            $request->input('device_name', 'api'),
        );

        return $this->tokenResponse($issued, $user, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $user = User::query()
            ->select(['id', 'name', 'email', 'password', 'role'])
            ->where('email', $email)
            ->first();

        if (! $this->passwordMatches($user, $request->string('password')->toString())) {
            return $this->errorResponse(__('messages.auth.invalid_credentials'), Response::HTTP_UNAUTHORIZED);
        }

        $issued = $this->tokens->issue(
            $user,
            $request->input('device_name', 'api'),
        );

        return $this->tokenResponse($issued, $user);
    }

    public function logout(Request $request): Response
    {
        $token = $request->attributes->get('apiToken');
        if ($token instanceof ApiToken) {
            $this->tokens->revoke($token);
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user();

        return new UserResource($user);
    }

    /**
     * Always runs one hash check, even for unknown emails, so response time
     * does not reveal which accounts exist.
     *
     * @phpstan-assert-if-true User $user
     */
    private function passwordMatches(?User $user, string $password): bool
    {
        $hash = $user instanceof User
            ? $user->password
            : Cache::rememberForever(
                'auth.dummy_password_hash',
                static fn (): string => Hash::make(Str::random(40)),
            );

        return Hash::check($password, $hash) && $user instanceof User;
    }
}
