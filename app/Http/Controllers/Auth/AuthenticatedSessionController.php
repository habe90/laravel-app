<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Sanctum\HasApiTokens;
use App\Providers\RouteServiceProvider;
use Location;
use App\Models\UserLog;
use Illuminate\Support\Facades\Cache;




class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        // Collection of user information
        $ipAddress = request()->ip();
        $userAgent = request()->header('User-Agent');
        $remainingAttempts = $this->getRemainingAttempts();



        // Determining the user's country
        $location = Location::get($ipAddress);
        $countryCode = $location->countryCode ?? null;

        // Setting the language
        $language = $this->getLanguageForCountry($countryCode);
        app()->setLocale($language);

        // Store user data in session
        session([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'country_code' => $countryCode,
            'language' => $language,
        ]);

        return view('auth.login')->with(compact('remainingAttempts'));
    }

    private function getRemainingAttempts()
    {
        $ipAddress = request()->ip();
        $attempts = Cache::get("login_attempts_$ipAddress", 0);
        $remainingAttempts = max(0, 3 - $attempts);

        return $remainingAttempts;
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|View
    {
        $credentials = $request->only('email', 'password');

        // Check if the IP address is blocked
        $ipAddress = request()->ip();
        if (Cache::get("blocked_ip_$ipAddress")) {
            $message = __('auth.ip_blocked_3h', ['time' => now()->addHours(3)->format('H:i')]);
            return view('auth.login')->withErrors([
                'email' => $message,
            ]);
        }

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // Update user statistics in the database
            $userLog = new UserLog();
            $userLog->user_id = auth()->id();
            $userLog->ip_address = $ipAddress;
            $userLog->user_agent = request()->header('User-Agent');
            $location = Location::get($ipAddress);
            $userLog->country_code = $location->countryCode ?? null;
            $userLog->language = $this->getLanguageForCountry($userLog->country_code);
            $userLog->platform = $this->getPlatform($userLog->user_agent);
            $userLog->save();

            // Reset login attempts for the current IP address
            Cache::forget("login_attempts_$ipAddress");

            return redirect()->intended(RouteServiceProvider::HOME);
        }

        // Increment login attempts for the current IP address
        $attempts = Cache::increment("login_attempts_$ipAddress");

        // Retrieve the number of login attempts for the current IP address
        $attempts = Cache::get("login_attempts_$ipAddress", 0);

        // If there were more than 3 attempts, block the IP address for 3 hours
        if ($attempts >= 3) {
            Cache::put("blocked_ip_$ipAddress", true, now()->addHours(3));
            Cache::forget("login_attempts_$ipAddress");
            $message = __('auth.ip_blocked_3h', ['time' => now()->addHours(3)->format('H:i')]);
            $remainingAttempts = 0;
        } else {
            $remainingAttempts = 3 - $attempts;
            $message = __('auth.failed', ['remainingAttempts' => $remainingAttempts]);
        }

        return view('auth.login')->withErrors([
            'email' => $message,
        ])->with('remainingAttempts', $remainingAttempts);
    }




    private function getPlatform($userAgent)
    {
        if (stripos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (stripos($userAgent, 'Macintosh') !== false) {
            return 'Mac';
        } else {
            return 'Unknown';
        }
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

    private function getLanguageForCountry($countryCode)
    {
        // List the available languages for your application here
        $availableLanguages = [
            'US' => 'en',
            'DE' => 'de',
            // ...
        ];

        return $availableLanguages[$countryCode] ?? 'en';
    }
}
