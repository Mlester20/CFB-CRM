<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Rbac;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($this->route('user')),
            ],
            'role' => ['required', 'string', Rule::in(Rbac::roles())],
        ];
    }

    /**
     * The last active Admin cannot be moved to another role.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var User $target */
                $target = $this->route('user');

                if ($this->input('role') !== Rbac::ADMIN && $target->isLastActiveAdmin()) {
                    $validator->errors()->add('role', 'The last active Admin cannot be demoted.');
                }
            },
        ];
    }
}
