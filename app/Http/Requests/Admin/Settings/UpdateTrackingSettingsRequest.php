<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrackingSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->hasRole('Super Admin') ?? false;
    }

    /**
     * Prepare the data for validation.
     * Intelligently extracts tracking IDs if an admin pasted the full script tag into an ID field.
     */
    protected function prepareForValidation(): void
    {
        foreach (['clarity_project_id', 'google_analytics_id', 'google_tag_manager_id', 'facebook_pixel_id'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        // Auto-extract Clarity project ID from full snippet
        if ($this->has('clarity_project_id') && is_string($this->clarity_project_id)) {
            if (preg_match('/clarity\.ms\/tag\/([a-zA-Z0-9_-]+)/i', $this->clarity_project_id, $matches)
                || preg_match('/[\'"]clarity[\'"]\s*,\s*[\'"]script[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/i', $this->clarity_project_id, $matches)) {
                $this->merge(['clarity_project_id' => $matches[1]]);
            }
        }

        // Auto-extract Google Tag Manager ID from snippet
        if ($this->has('google_tag_manager_id') && is_string($this->google_tag_manager_id)) {
            if (str_contains($this->google_tag_manager_id, 'googletagmanager.com') && preg_match('/(GTM-[A-Z0-9]+)/i', $this->google_tag_manager_id, $matches)) {
                $this->merge(['google_tag_manager_id' => strtoupper($matches[1])]);
            } else {
                $this->merge(['google_tag_manager_id' => strtoupper($this->google_tag_manager_id)]);
            }
        }

        // Auto-extract Google Analytics Measurement ID from snippet
        if ($this->has('google_analytics_id') && is_string($this->google_analytics_id)) {
            if ((str_contains($this->google_analytics_id, 'googletagmanager.com') || preg_match('/\bgtag\s*\(/i', $this->google_analytics_id)) && preg_match('/(G-[A-Z0-9]+)/i', $this->google_analytics_id, $matches)) {
                $this->merge(['google_analytics_id' => strtoupper($matches[1])]);
            } else {
                $this->merge(['google_analytics_id' => strtoupper($this->google_analytics_id)]);
            }
        }

        // Auto-extract Meta Pixel ID from snippet
        if ($this->has('facebook_pixel_id') && is_string($this->facebook_pixel_id)) {
            if (preg_match('/\bfbq\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]([0-9]+)[\'"]\s*(?=[,)])/i', $this->facebook_pixel_id, $matches)) {
                $this->merge(['facebook_pixel_id' => $matches[1]]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clarity_enabled' => ['required', 'boolean'],
            'clarity_project_id' => [Rule::requiredIf($this->boolean('clarity_enabled')), 'nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9]+$/'],
            'google_analytics_enabled' => ['required', 'boolean'],
            'google_analytics_id' => [Rule::requiredIf($this->boolean('google_analytics_enabled')), 'nullable', 'string', 'max:50', 'regex:/^G-[A-Z0-9]+$/'],
            'google_tag_manager_enabled' => ['required', 'boolean'],
            'google_tag_manager_id' => [Rule::requiredIf($this->boolean('google_tag_manager_enabled')), 'nullable', 'string', 'max:50', 'regex:/^GTM-[A-Z0-9]+$/'],
            'facebook_pixel_enabled' => ['required', 'boolean'],
            'facebook_pixel_id' => [Rule::requiredIf($this->boolean('facebook_pixel_enabled')), 'nullable', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'custom_scripts_enabled' => ['required', 'boolean'],
            'custom_head_scripts' => ['nullable', 'string', 'max:65535'],
            'custom_body_scripts' => ['nullable', 'string', 'max:65535'],
            'exclude_admins' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'clarity_project_id.required' => 'Enter a Clarity project ID before enabling Clarity.',
            'clarity_project_id.regex' => 'Enter a Clarity project ID containing only letters and numbers.',
            'google_analytics_id.required' => 'Enter a GA4 measurement ID before enabling Google Analytics.',
            'google_analytics_id.regex' => 'Enter a GA4 measurement ID starting with G-, followed by letters and numbers.',
            'google_tag_manager_id.required' => 'Enter a container ID before enabling Google Tag Manager.',
            'google_tag_manager_id.regex' => 'Enter a container ID starting with GTM-, followed by letters and numbers.',
            'facebook_pixel_id.required' => 'Enter a pixel ID before enabling Meta Pixel.',
            'facebook_pixel_id.regex' => 'Enter a numeric Meta Pixel ID.',
        ];
    }
}
