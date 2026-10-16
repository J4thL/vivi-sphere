<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Public self-registration. Always creates a "client" account —
     * admin and stylist accounts are provisioned by an admin instead
     * (see StylistController@store, which can also create the login).
     */
    public function register(Request $request)
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ])->validate();

        // The account and its client record go in together; a half-registered user (a login with no
        // client record) would otherwise be stuck unable to book.
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'client',
            ]);

            Client::create([
                'user_id' => $user->id,
                'name' => $user->name,
            ]);

            return $user;
        });

        // Fires Laravel's built-in "verify your email" notification, if a
        // mail driver is configured. Without one this is a no-op, so
        // registration still succeeds in demo/local environments.
        event(new Registered($user));

        $token = $user->createToken('web')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Checked after the password so this can't be used to probe which emails are disabled.
        if ($user->isDisabled()) {
            return response()->json(['message' => 'This account has been disabled. Please contact the salon admin.'], 403);
        }

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'user' => $user->load('stylist'),
            'token' => $token,
        ]);
    }

    /** Any signed-in person can change their own password (staff use this to replace the temporary one). */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Your current password is incorrect.', 'errors' => ['current_password' => ['Your current password is incorrect.']]], 422);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        // Sign out every other device; this one stays logged in.
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id ?? 0)->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('stylist'));
    }
}
