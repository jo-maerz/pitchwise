<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One layer of Fabric.js objects per page. Others load the shared layer into their browser,
 * so only drawn shapes and text are accepted: no images or patterns that could fetch a URL.
 */
class SaveAnnotationsRequest extends FormRequest
{
    public const MAX_BYTES = 2_000_000;

    private const ALLOWED_TYPES = ['path', 'text', 'i-text', 'itext', 'textbox', 'group', 'line', 'polyline', 'polygon', 'rect', 'circle', 'ellipse', 'triangle'];

    private const FORBIDDEN_KEYS = ['src', 'source', 'crossOrigin', 'filters'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pages' => ['present', 'array', 'max:500'],
            'pages.*' => ['present', 'array', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (strlen((string) json_encode($this->input('pages'))) > self::MAX_BYTES) {
                $validator->errors()->add('pages', 'Too many annotations to save at once.');
            } elseif (! $this->onlyDrawnObjects($this->input('pages'))) {
                $validator->errors()->add('pages', 'Only drawings, text and stickers can be saved.');
            }
        }];
    }

    private function onlyDrawnObjects(array $node): bool
    {
        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, self::FORBIDDEN_KEYS, true)) {
                return false;
            }
            if ($key === 'type' && ! in_array(strtolower((string) $value), self::ALLOWED_TYPES, true)) {
                return false;
            }
            if (is_array($value) && ! $this->onlyDrawnObjects($value)) {
                return false;
            }
        }

        return true;
    }
}
