<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Validator;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Laravel\Passport\Client as OClient;
use GuzzleHttp\Client;
use Guzzle\Http\EntityBody;
use GuzzleHttp\Client as GuzzleHttp;

use Guzzle\Http\Message\Response;




class AuthController extends Controller
{
    /**
     * Registration Req
     */



    public $successStatus = 200;

    public function register(Request $request)
    {
        /*  $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->save();
        return response()->json(['data' => $user]); */

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 401);
        }
        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        $success['token'] =  $user->createToken('MyApp')->accessToken;
        $success['name'] =  $user->name;
        return response()->json(['success' => $success], $this->successStatus);
    }

    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);
        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if (!auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        /* ------------ Create a new personal access token for the user. ------------ */
        $tokenData = auth()->user()->createToken('MyApiToken');
        $token = $tokenData->accessToken;
        $expiration = $tokenData->token->expires_at->diffInSeconds(Carbon::now());

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiration
        ]);
    }

    public function getUser()
    {
        return response()->json(auth()->user());
    }

    public function logout()
    {
        $token = auth()->user()->token();

        /* --------------------------- revoke access token -------------------------- */
        $token->revoke();
        $token->delete();

        /* -------------------------- revoke refresh token -------------------------- */
        $refreshTokenRepository = app(RefreshTokenRepository::class);
        $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($token->id);

        return response()->json(['message' => 'Logged out successfully']);
    }

    /* ----------------- get both access_token and refresh_token ---------------- */
    public function loginGrant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $baseUrl = url('/');
        $response = Http::post("{$baseUrl}/oauth/token", [
            'username' => $request->email,
            'password' => $request->password,
            'client_id' => config('passport.password_grant_client.id'),
            'client_secret' => config('passport.password_grant_client.secret'),
            'grant_type' => 'password'
        ]);

        $result = json_decode($response->getBody(), true);
        if (!$response->ok()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return response()->json($result);
    }

    /* -------------------------- refresh access_token -------------------------- */
    public function refreshToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $baseUrl = url('/');
        $response = Http::post("{$baseUrl}/oauth/token", [
            'refresh_token' => $request->refresh_token,
            'client_id' => config('passport.password_grant_client.id'),
            'client_secret' => config('passport.password_grant_client.secret'),
            'grant_type' => 'refresh_token'
        ]);

        $result = json_decode($response->getBody(), true);
        if (!$response->ok()) {
            return response()->json(['error' => $result['error_description']], 401);
        }
        return response()->json($result);
    }
}