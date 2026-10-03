<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Base controller for all JSON API controllers.
 * Loads the LavaLust Api library (JWT, CORS, JSON responses) and the database.
 */
class ApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');   // validates secrets + sends CORS headers
        $this->call->database();       // $this->db
    }

    /**
     * Read the request body as an associative array (JSON or form data).
     * Values are returned raw (not HTML-escaped); the React frontend escapes on output.
     */
    protected function input(): array
    {
        $raw = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false || (is_string($raw) && ltrim($raw) !== '' && ltrim($raw)[0] === '{')) {
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        parse_str((string) $raw, $data);
        return is_array($data) ? $data : [];
    }

    /** Client IP used for rate-limit keys. */
    protected function client_ip(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded) {
            return trim(explode(',', $forwarded)[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /** Public user fields only (never the password hash). */
    protected function public_user(array $u): array
    {
        return [
            'id'         => (int) $u['id'],
            'username'   => $u['username'],
            'email'      => $u['email'],
            'role'       => $u['role'],
            'created_at' => $u['created_at'] ?? null,
        ];
    }
}
