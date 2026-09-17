<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Проверяет email/пароль и выдаёт Sanctum-токен. Бросает ValidationException
     * при неверных данных - Laravel сам превратит его в 422 с телом
     * {"message": "...", "errors": {"email": [...]}}, отдельный catch не нужен.
     */
    public function login(string $email, string $password): string
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        return $user->createToken('api')->plainTextToken;
    }
}
