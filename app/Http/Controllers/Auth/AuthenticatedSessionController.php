<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'url' => env("APP_URL")
        ]);
    }
    public function providerRedirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }
    public function providerCallback($provider, Request $request)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            $user = User::where([
                'email' => $socialUser->getEmail()
            ])->first();
            if ($user && $user->provider !== $provider) {
                return redirect()->route("login")->withErrors('This email uses a diffrent methode to login.');
            }
            $user = User::where([
                'provider_id' => $socialUser->id,
                'provider' => $provider
            ])->first();
            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->name,
                    'email' => $socialUser->getEmail(),
                    'username' => User::genUserName($socialUser->getNickname()),
                    'avatar' => $socialUser->getAvatar(),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_token' => $socialUser->token,
                    'email_verified_at' => now(),
                ]);
            }
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            return redirect()->route("login")->withErrors(['Something went wrong. Please try again.', $e->getMessage()]);
        }
    }


    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
