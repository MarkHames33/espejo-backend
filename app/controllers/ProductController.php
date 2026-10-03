<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once __DIR__ . '/ApiController.php';

/**
 * Product CRUD. Every action requires a valid JWT (Authorization: Bearer <token>).
 */
class ProductController extends ApiController
{
    /** Every action calls guard() first: only authenticated users may continue. */
    private function guard(): array
    {
        return $this->api->require_jwt();
    }

    /** GET /api/products?search=term */
    public function index()
    {
        $this->guard();

        $search = trim((string) ($_GET['search'] ?? ''));

        $q = $this->db->table('products');
        if ($search !== '') {
            $q->like('product_name', '%' . $search . '%');
        }
        $rows = $q->order_by('id', 'DESC')->get_all();

        $this->api->respond([
            'data'  => array_map([$this, 'format'], $rows ?: []),
            'count' => count($rows ?: []),
        ]);
    }

    /** GET /api/products/{id} */
    public function show($id)
    {
        $this->guard();
        $this->api->respond(['data' => $this->format($this->find_or_404($id))]);
    }

    /** POST /api/products */
    public function store()
    {
        $this->guard();

        $data = $this->validate($this->input(), true);

        $new_id = $this->db->table('products')->insert($data);

        $this->api->respond([
            'message' => 'Product created successfully',
            'data'    => $this->format($this->find_or_404($new_id)),
        ], 201);
    }

    /** PUT|PATCH /api/products/{id} */
    public function update($id)
    {
        $this->guard();
        $this->find_or_404($id);

        $partial = ($_SERVER['REQUEST_METHOD'] ?? '') === 'PATCH';
        $data    = $this->validate($this->input(), !$partial);

        if (empty($data)) {
            $this->api->respond_error('No valid fields to update.', 422);
        }

        $this->db->table('products')->where(['id' => (int) $id])->update($data);

        $this->api->respond([
            'message' => 'Product updated successfully',
            'data'    => $this->format($this->find_or_404($id)),
        ]);
    }

    /** DELETE /api/products/{id} */
    public function destroy($id)
    {
        $this->guard();
        $this->find_or_404($id);

        $this->db->table('products')->where(['id' => (int) $id])->delete();

        $this->api->respond(['message' => 'Product deleted successfully']);
    }

    // ------------------------------------------------------------------

    private function find_or_404($id): array
    {
        if (!ctype_digit((string) $id)) {
            $this->api->respond_error('Product not found.', 404);
        }
        $row = $this->db->table('products')->where(['id' => (int) $id])->get();
        if (!$row) {
            $this->api->respond_error('Product not found.', 404);
        }
        return $row;
    }

    /**
     * Validate input. $require_all = true for POST/PUT, false for PATCH.
     */
    private function validate(array $in, bool $require_all): array
    {
        $errors = [];
        $data   = [];

        if (array_key_exists('product_name', $in) || $require_all) {
            $name = trim((string) ($in['product_name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                $errors['product_name'] = 'Product name is required (max 100 characters).';
            } else {
                $data['product_name'] = $name;
            }
        }

        if (array_key_exists('description', $in)) {
            $data['description'] = trim((string) $in['description']);
        } elseif ($require_all) {
            $data['description'] = '';
        }

        if (array_key_exists('price', $in) || $require_all) {
            $price = $in['price'] ?? null;
            if (!is_numeric($price) || $price < 0 || $price > 99999999.99) {
                $errors['price'] = 'Price must be a number between 0 and 99999999.99.';
            } else {
                $data['price'] = number_format((float) $price, 2, '.', '');
            }
        }

        if (array_key_exists('quantity', $in) || $require_all) {
            $qty = $in['quantity'] ?? null;
            if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 0) {
                $errors['quantity'] = 'Quantity must be a whole number (0 or more).';
            } else {
                $data['quantity'] = (int) $qty;
            }
        }

        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'errors' => $errors, 'status' => 422], 422);
        }

        return $data;
    }

    private function format(array $p): array
    {
        return [
            'id'           => (int) $p['id'],
            'product_name' => $p['product_name'],
            'description'  => $p['description'],
            'price'        => (float) $p['price'],
            'quantity'     => (int) $p['quantity'],
            'created_at'   => $p['created_at'],
        ];
    }
}
