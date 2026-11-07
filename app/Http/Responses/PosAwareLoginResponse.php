<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Modules\Platform\Support\AuthenticatedLanding;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

final class PosAwareLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return redirect('/login');
        }

        $landing = app(AuthenticatedLanding::class);
        if (($scopeSetup = $landing->scopeSetupDestination($user)) !== null) {
            $request->session()->forget('url.intended');

            return redirect()->to($scopeSetup);
        }

        return redirect()->intended($landing->destination($user));
    }
}
