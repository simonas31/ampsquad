<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * Signs the customer out of the shop only: the session is not thrown
     * away, as a person working in the admin panel in the same browser would
     * be signed out of it too. Its id and token change instead.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
