<?php

namespace Ismaelcmajada\LaravelAutoCrud\Services;

class ApiResponseFactory
{
    public function success($data = null, $message = null, array $meta = [], $status = 200)
    {
        $payload = [
            'success' => true,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public function error($message, array $errors = [], $status = 400)
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
