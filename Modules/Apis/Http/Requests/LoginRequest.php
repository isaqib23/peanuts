<?php

namespace Modules\Apis\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends BaseFormRequest
{
    protected $availableAttributes = 'user::attributes.users';
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
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
}
