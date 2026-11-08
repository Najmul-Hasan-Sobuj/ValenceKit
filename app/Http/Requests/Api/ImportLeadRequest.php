<?php

namespace App\Http\Requests\Api;

use App\Models\Admin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Passport\AccessToken;

class ImportLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('leads');
        $token = $actor instanceof Admin ? $actor->currentAccessToken() : null;

        return $actor instanceof Admin && $actor->is_active && ! $actor->must_change_password
            && $actor->can('leads.import') && $token instanceof AccessToken
            && $token->can('leads:import') && $token->expires_at?->isFuture();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'in:google_maps'],
            'source_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254'],
            'address' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'maps_url' => ['nullable', 'url:http,https', 'max:2048'],
            'category' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'review_count' => ['nullable', 'integer', 'between:0,2147483647'],
            'query' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'array:description,hours,images,socials,latitude,longitude'],
            'details.description' => ['nullable', 'string', 'max:5000'],
            'details.hours' => ['sometimes', 'array', 'max:14'],
            'details.hours.*' => ['string', 'max:500'],
            'details.images' => ['sometimes', 'array', 'max:10'],
            'details.images.*' => ['url:http,https', 'max:2048'],
            'details.socials' => ['sometimes', 'array:facebook,instagram,linkedin,twitter,youtube,tiktok'],
            'details.socials.*' => ['url:http,https', 'max:2048'],
            'details.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'details.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
