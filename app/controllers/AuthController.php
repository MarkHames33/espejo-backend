<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once __DIR__ . '/ApiController.php';

class AuthController extends ApiController
{
    /** POST /api/auth/register */
    public function register()
    {
        $this->api->rate_limit('register_' . $this->client_ip(), 10, 60);

        $in       = $this->input();
        $username = trim((string) ($in['username'] ?? ''));
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] = 'Username must be 3-50 characters (letters, numbers, . _ -).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'errors' => $errors, 'status' => 422], 422);
        }

        $exists = $this->db->table('users')->where('username', $username)->or_where('email', $email)->get();
        if ($exists) {
            $this->api->respond_error('Username or email is already taken.', 409);
        }

        $id = $this->db->table('users')->insert([
            'username' => $username,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => 'user',
        ]);

        $user = $this->db->table('users')->where(['id' => $id])->get();

        $tokens = $this->api->issue_tokens([
            'id'   => (int) $user['id'],
            'role' => $user['role'],
        ]);

        $this->api->respond([
            'message' => 'Registration successful',
            'user'    => $this->public_user($user),
            'tokens'  => $tokens,
        ], 201);
    }

    /** POST /api/auth/login   body: { "login": "email or username", "password": "..." } */
    public function login()
    {
        $this->api->rate_limit('login_' . $this->client_ip(), 15, 60);

        $in       = $this->input();
        $login    = trim((string) ($in['login'] ?? $in['email'] ?? $in['username'] ?? ''));
        $password = (string) ($in['password'] ?? '');

        if ($login === '' || $password === '') {
            $this->api->respond_error('Login and password are required.', 422);
        }

        $user = $this->db->table('users')
            ->where('email', strtolower($login))
            ->or_where('username', $login)
            ->get();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid credentials.', 401);
        }

        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error('This account is disabled.', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'   => (int) $user['id'],
            'role' => $user['role'],
        ]);

        $this->api->respond([
            'message' => 'Login successful',
            'user'    => $this->public_user($user),
            'tokens'  => $tokens,
        ]);
    }

    /** POST /api/auth/refresh   body: { "refresh_token": "..." } */
    public function refresh()
    {
        $in    = $this->input();
        $token = (string) ($in['refresh_token'] ?? '');
        if ($token === '') {
            $this->api->respond_error('refresh_token is required.', 422);
        }
        // Validates, rotates the token and responds with new tokens.
        $this->api->refresh_access_token($token);
    }

    /** POST /api/auth/logout   body: { "refresh_token": "..." } */
    public function logout()
    {
        $in    = $this->input();
        $token = (string) ($in['refresh_token'] ?? '');
        if ($token !== '') {
            $this->api->revoke_refresh_token($token);
        }
        $this->api->respond(['message' => 'Logged out successfully']);
    }

    /** GET /api/auth/me   (requires Bearer token) */
    public function me()
    {
        $payload = $this->api->require_jwt();
        $user = $this->db->table('users')->where(['id' => $payload['sub']])->get();
        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }
        $this->api->respond(['user' => $this->public_user($user)]);
    }
}
