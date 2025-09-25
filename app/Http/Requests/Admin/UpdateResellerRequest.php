<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResellerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reseller = $this->route('reseller');

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($reseller->id),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'max_users_quota' => 'nullable|integer|min:0|max:10000',
            'max_licenses_quota' => 'nullable|integer|min:0|max:100000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The reseller name is required.',
            'email.required' => 'The email address is required.',
            'email.unique' => 'This email address is already in use.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'max_users_quota.integer' => 'The user quota must be a valid number.',
            'max_users_quota.min' => 'The user quota cannot be negative.',
            'max_users_quota.max' => 'The user quota cannot exceed 10,000.',
            'max_licenses_quota.integer' => 'The license quota must be a valid number.',
            'max_licenses_quota.min' => 'The license quota cannot be negative.',
            'max_licenses_quota.max' => 'The license quota cannot exceed 100,000.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'max_users_quota' => 'user quota',
            'max_licenses_quota' => 'license quota',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $reseller = $this->route('reseller');
            
            // Check if reducing user quota below current usage
            if ($this->has('max_users_quota') && 
                $this->max_users_quota !== null && 
                $this->max_users_quota < $reseller->current_users_count) {
                $validator->errors()->add(
                    'max_users_quota', 
                    'Cannot set user quota below current usage (' . $reseller->current_users_count . ').'
                );
            }

            // Check if reducing license quota below current usage
            if ($this->has('max_licenses_quota') && 
                $this->max_licenses_quota !== null && 
                $this->max_licenses_quota < $reseller->current_licenses_count) {
                $validator->errors()->add(
                    'max_licenses_quota', 
                    'Cannot set license quota below current usage (' . $reseller->current_licenses_count . ').'
                );
            }
        });
    }
}
