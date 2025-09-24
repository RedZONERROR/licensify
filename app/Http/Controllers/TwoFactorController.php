<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
        $this->middleware('auth');
    }

    /**
     * Show 2FA setup page
     */
    public function show(): View
    {
        $user = auth()->user();
        
        if ($user->hasTwoFactorEnabled()) {
            return view('auth.two-factor.manage', [
                'user' => $user,
                'recoveryCodes' => $user->getRecoveryCodes()
            ]);
        }

        $secret = $this->google2fa->generateSecretKey();
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCode = $this->generateQrCode($qrCodeUrl);

        return view('auth.two-factor.setup', [
            'secret' => $secret,
            'qrCode' => $qrCode,
            'user' => $user
        ]);
    }

    /**
     * Enable 2FA for the user
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'secret' => 'required|string',
            'code' => 'required|string|size:6'
        ]);

        $user = auth()->user();
        $secret = $request->secret;
        $code = $request->code;

        // Verify the code
        if (!$this->google2fa->verifyKey($secret, $code)) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        // Save the secret and enable 2FA
        $user->update([
            'two_factor_secret' => encrypt($secret)
        ]);

        $user->confirmTwoFactor();

        // Generate recovery codes
        $recoveryCodes = $user->generateRecoveryCodes();

        return redirect()->route('two-factor.show')
            ->with('status', '2FA has been enabled successfully!')
            ->with('recoveryCodes', $recoveryCodes);
    }

    /**
     * Disable 2FA for the user
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|current_password'
        ]);

        $user = auth()->user();
        $user->disable2FA();

        return redirect()->route('two-factor.show')
            ->with('status', '2FA has been disabled successfully!');
    }

    /**
     * Show recovery codes
     */
    public function recoveryCodes(): View
    {
        $user = auth()->user();
        
        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.show');
        }

        return view('auth.two-factor.recovery-codes', [
            'recoveryCodes' => $user->getRecoveryCodes()
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|current_password'
        ]);

        $user = auth()->user();
        
        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.show');
        }

        $recoveryCodes = $user->generateRecoveryCodes();

        return redirect()->route('two-factor.recovery-codes')
            ->with('status', 'Recovery codes have been regenerated!')
            ->with('recoveryCodes', $recoveryCodes);
    }

    /**
     * Show 2FA challenge page
     */
    public function challenge(): View
    {
        return view('auth.two-factor.challenge');
    }

    /**
     * Verify 2FA challenge
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string',
            'recovery' => 'sometimes|boolean'
        ]);

        $user = auth()->user();
        $code = $request->code;
        $isRecovery = $request->boolean('recovery');

        if ($isRecovery) {
            // Verify recovery code
            if (!$user->useRecoveryCode($code)) {
                return back()->withErrors(['code' => 'Invalid recovery code.']);
            }
        } else {
            // Verify TOTP code
            $secret = decrypt($user->two_factor_secret);
            if (!$this->google2fa->verifyKey($secret, $code)) {
                return back()->withErrors(['code' => 'Invalid verification code.']);
            }
        }

        // Mark 2FA as verified for this session
        session(['2fa_verified' => true, '2fa_verified_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Generate QR code SVG
     */
    protected function generateQrCode(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        return $writer->writeString($url);
    }
}