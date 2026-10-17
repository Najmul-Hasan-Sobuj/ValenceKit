<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrackingSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('settings.tracking.edit') ?? false;
    }

    /**
     * Prepare the data for validation.
     * Intelligently extracts tracking IDs if an admin pasted the full script tag into an ID field.
     */
    protected function prepareForValidation(): void
    {
        // Auto-extract Clarity project ID from full snippet
        if ($this->has('clarity_project_id') && is_string($this->clarity_project_id)) {
            if (preg_match('/clarity\.ms\/tag\/([a-zA-Z0-9_-]+)/i', $this->clarity_project_id, $matches)
                || preg_match('/"clarity",\s*"script",\s*"([a-zA-Z0-9_-]+)"/i', $this->clarity_project_id, $matches)) {
                $this->merge(['clarity_project_id' => $matches[1]]);
            }
        }

        // Auto-extract Google Tag Manager ID from snippet
        if ($this->has('google_tag_manager_id') && is_string($this->google_tag_manager_id)) {
            if (preg_match('/(GTM-[A-Z0-9]+)/i', $this->google_tag_manager_id, $matches)) {
                $this->merge(['google_tag_manager_id' => strtoupper($matches[1])]);
            }
        }

        // Auto-extract Google Analytics Measurement ID from snippet
        if ($this->has('google_analytics_id') && is_string($this->google_analytics_id)) {
            if (preg_match('/(G-[A-Z0-9]+)/i', $this->google_analytics_id, $matches)) {
                $this->merge(['google_analytics_id' => strtoupper($matches[1])]);
            }
        }

        // Auto-extract Meta Pixel ID from snippet
        if ($this->has('facebook_pixel_id') && is_string($this->facebook_pixel_id)) {
            if (preg_match('/fbq\([\'"]init[\'"],\s*[\'"]([0-9]+)[\'"]\)/i', $this->facebook_pixel_id, $matches)) {
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
            'clarity_project_id' => ['nullable', 'string', 'max:50'],
            'google_analytics_enabled' => ['required', 'boolean'],
            'google_analytics_id' => ['nullable', 'string', 'max:50'],
            'google_tag_manager_enabled' => ['required', 'boolean'],
            'google_tag_manager_id' => ['nullable', 'string', 'max:50'],
            'facebook_pixel_enabled' => ['required', 'boolean'],
            'facebook_pixel_id' => ['nullable', 'string', 'max:50'],
            'custom_scripts_enabled' => ['required', 'boolean'],
            'custom_head_scripts' => ['nullable', 'string', 'max:65535'],
            'custom_body_scripts' => ['nullable', 'string', 'max:65535'],
            'exclude_admins' => ['required', 'boolean'],
        ];
    }
}
