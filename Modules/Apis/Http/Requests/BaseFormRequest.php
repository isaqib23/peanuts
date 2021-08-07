<?php

namespace Modules\Apis\Http\Requests;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Available attributes.
     *
     * @var string
     */
    protected $availableAttributes = '';

    /**
     * Current processed locale.
     *
     * @var string
     */
    protected $localeKey;

    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        $attributes = trans($this->availableAttributes) ?: [];

        if (! is_array($attributes)) {
            return [];
        }

        return array_map('mb_strtolower', array_dot($attributes));
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        $attributesAndRules = $this->parseRules($this->rules());

        $messages = [];

        foreach ($attributesAndRules as $attributeAndRule) {
            $rule = last(explode('.', $attributeAndRule));

            $messages[$attributeAndRule] = trans("core::validation.{$rule}");
        }

        return $messages;
    }

    /**
     * Parse rules for the given attributes.
     *
     * Gives
     * [
     *  'name' => 'required|size:60',
     * ]
     *
     * Returns
     * [
     *  'name.required',
     *  'name.size',
     * ]
     *
     * @param array $rules
     * @return array
     */
    protected function parseRules(array $rules)
    {
        $attributesAndRules = [];

        foreach ($rules as $attribute => $rulesList) {
            if (! is_array($rulesList)) {
                $rulesList = explode('|', $rulesList);
            }

            foreach ($rulesList as $rule) {
                if ($rule instanceof Closure) {
                    continue;
                }

                if (strpos($rule, ':') !== false) {
                    list($rule) = explode(':', $rule, 2);
                }

                $attributesAndRules[] = "{$attribute}.{$rule}";
            }
        }

        return $attributesAndRules;
    }

    /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => $validator->messages()->first()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
