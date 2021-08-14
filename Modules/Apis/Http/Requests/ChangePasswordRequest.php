<?php

namespace Modules\Apis\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'password' => ['required', 'confirmed', 'min:6']
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return ChangePasswordRequest
     */
    public function bcryptPassword()
    {
        if ($this->filled('password')) {
            return $this->merge(['password' => bcrypt($this->password)]);
        }

        unset($this['password']);
    }
}
