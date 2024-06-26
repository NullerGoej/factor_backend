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
            'image' => "https://t4.ftcdn.net/jpg/00/64/67/63/360_F_64676383_LdbmhiNM6Ypzb3FM4PPuFP9rHe7ri8Ju.jpg"
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
    public function twoFactorAuthRequest(Request $request)
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

        $unique_id = $phone->two_factor_secret . "_" . time();

        // Create request datetime
        $request = new TwoFactorRequest([
            'unique_id' => Hash::make($unique_id),
            'ip_address' => $request->ip_address,
            'action' => $request->action,
            'device_id' => $phone->id,
        ]);

        $request->save();
        return response()->json(['message' => 'Request created successfully', 'unique_id' => $unique_id], 200);
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
                $twoFactorSecret = \ParagonIE\ConstantTime\Base32::encodeUpper(random_bytes(25));

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

        $user = User::find($phone->user_id);

        return response()->json(['message' => '2FA verified successfully', 'two_factor_6_digit' => $phone->two_factor_6_digit, 'email' => $user->email], 200);
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
    public function twoFactorAuthRequestStatus(Request $request)
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
    public function twoFactorAuthRequestAccept(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $phone = Phone::where('two_factor_secret', $token)->first();

        if (!$phone) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $unique_id = $request->unique_id;

        if (!$unique_id) {
            return response()->json(['error' => 'Unique ID required'], 400);
        }

        $request = TwoFactorRequest::where('unique_id', $unique_id)->where('accepted', 0)->where('device_id', $phone->id)->first();

        if (!$request) {
            return response()->json(['error' => 'No request found'], 400);
        }

        $request->accepted = 1;
        $request->save();

        return response()->json(['message' => 'Request accepted successfully'], 200);
    }

    public function user(Request $request)
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

        $user = $accessToken->tokenable;

        $phone = Phone::where('user_id', $user->id)->first();

        if (!$phone) {
            $user->phone = 0;
        } else {
            $user->phone = $phone->two_factor_setup;
        }

        return response()->json(['user' => $user], 200);
    }
    // logout
    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        // Revoke the token.
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        $accessToken->delete();

        return response()->json(['message' => 'User logged out successfully'], 200);
    }

    public function logoutAll(Request $request)
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

        // Revoke all tokens.
        $user->tokens()->delete();

        return response()->json(['message' => 'User logged out on all machines successfully'], 200);
    }
    // find specific auth request
    public function findAuthRequest(Request $request)
    {
        $unique_id = $request->unique_id;

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

        // Check if the user has already set up 2FA.
        $phone = Phone::where('user_id', $user->id)->first();

        if (!$phone) {
            return response()->json(['error' => '2FA not set up'], 400);
        }

        // Assuming $phone->id is sanitized and valid
        $requests = TwoFactorRequest::where("device_id", $phone->id)->get();

        // Use collection methods to find the matched request
        $matchedRequest = $requests->first(function ($request) use ($unique_id) {
            return Hash::check($unique_id, $request->unique_id);
        });

        // Handle the response based on whether a matched request was found
        if (is_null($matchedRequest)) {
            return response()->json(['error' => 'No request found'], 400);
        }

        $matchedRequestArray = $matchedRequest->toArray();

        // Remove the unique_id from the array
        unset($matchedRequestArray['unique_id']);

        // Return the modified array in the response
        return response()->json($matchedRequestArray, 200);
    }
}
