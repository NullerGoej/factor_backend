<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Phone;
use App\Models\TwoFactorRequest;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string',
        ]);

        $user = new User([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->save();

        return response()->json(['message' => 'User registered successfully'], 201);
    }
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('login')->plainTextToken;
            return response()->json(['token' => $token], 200);
        } else {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }
    }
    public function twoFactorAuth(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        // Retrieve the personal access token instance.
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        // Retrieve the associated user.
        $user = $accessToken->tokenable;

        // Check if the token has the 'login' ability.
        if (!$accessToken->can('login')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if the user has already set up 2FA.
        $phone = Phone::where('user_id', $user->id)->first();

        if (!$phone) {
            return response()->json(['error' => '2FA not set up'], 400);
        }

        if ($phone->two_factor_setup !== 2) {
            return response()->json(['error' => 'Device not linked'], 400);
        }

        // Create request datetime
        $request = new TwoFactorRequest([
            'unique_id' => Hash::make($phone->two_factor_secret . "_" . time()),
            'ip_address' => $request->ip_address,
            'action' => $request->action,
            'device_id' => $phone->id,
        ]);

        $request->save();
        return response()->json(['message' => '2FA request sent successfully'], 200);
    }
    // public function twoFactorAuthSetup(Request $request)
    public function twoFactorAuthSetup(Request $request, $step = 1)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        // Retrieve the personal access token instance.
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        // Retrieve the associated user.
        $user = $accessToken->tokenable;

        // Check if the token has the 'login' ability.
        if (!$accessToken->can('login')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        switch ($step) {
            case 1:
                // Check if the user has already set up 2FA.
                $phone = Phone::where('user_id', $user->id)->first();

                if ($phone && $phone->two_factor_setup === 0) {
                    return response()->json(['qr_code' => urlencode($phone->two_factor_secret)], 200);
                } elseif ($phone && $phone->two_factor_setup === 1) {
                    return response()->json(['error' => '2FA already verified'], 400);
                } elseif ($phone && $phone->two_factor_setup === 2) {
                    return response()->json(['error' => '2FA already set up'], 400);
                }

                // Generate a 2FA secret key.
                $twoFactorSecret = \ParagonIE\ConstantTime\Base32::encodeUpper(random_bytes(15));

                // Create entry in the phones table.
                $phone = new Phone([
                    'device' => null,
                    'ip_address' => null,
                    'user_id' => $user->id,
                    'two_factor_secret' => $twoFactorSecret,
                    'two_factor_setup' => 0,
                    'two_factor_6_digit' => null,
                ]);

                $phone->save();

                // Generate a 2FA QR code.
                $twoFactorQRCode = urlencode($phone->two_factor_secret);

                return response()->json(['qr_code' => $twoFactorQRCode], 200);
            case 2:
                // Check if 6 digit code is provided.
                if (!$request->two_factor_6_digit) {
                    return response()->json(['error' => '6 digit code required'], 400);
                }

                // Check if the user has already set up 2FA.
                $phone = Phone::where('user_id', $user->id)->first();

                if (!$phone) {
                    return response()->json(['error' => '2FA not set up'], 400);
                }

                if ($phone->two_factor_setup === 0) {
                    return response()->json(['error' => 'Device not linked'], 400);
                }

                // Check if the 6 digit code is correct.
                if ($phone->two_factor_6_digit != $request->two_factor_6_digit) {
                    return response()->json(['error' => 'Invalid 6 digit code'], 400);
                }

                // Update the user's phone record.
                $phone->two_factor_setup = 2;
                $phone->save();

                return response()->json(['message' => '2FA setup successfully'], 200);
        }
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'User logged out successfully'], 200);
    }
    // scan qr code
    public function twoFactorAuthVerify(Request $request)
    {
        $qr_code = $request->qr_code;

        // find phone by qr code
        $phone = Phone::where('two_factor_secret', $qr_code)->first();

        if (!$phone) {
            return response()->json(['error' => 'Invalid QR code', 'code' => $qr_code], 401);
        }

        // update user phone
        $phone->device = $request->device;
        $phone->ip_address = $request->ip_address;
        $phone->two_factor_setup = 1;
        $phone->two_factor_6_digit = rand(100000, 999999);
        $phone->save();

        return response()->json(['message' => '2FA verified successfully','two_factor_6_digit' => $phone->two_factor_6_digit], 200);
    }
    // check if 2fa is verified
    public function twoFactorAuthStatus(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $phone = Phone::where('two_factor_secret', $token)->first();

        if (!$phone) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        if ($phone->two_factor_setup === 0) {
            return response()->json(['error' => 'It has been resetted'], 400);
        }

        return response()->json(['two_factor_setup' => $phone->two_factor_setup], 200);
    }
    // check for new 2fa request
    public function twoFactorAuthRequest(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $phone = Phone::where('two_factor_secret', $token)->first();

        if (!$phone) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $request = TwoFactorRequest::where('device_id', $phone->id)->where('accepted', 0)->first();

        if (!$request) {
            return response()->json(['error' => 'No request found'], 400);
        }

        return response()->json(['request' => $request], 200);
    }
}