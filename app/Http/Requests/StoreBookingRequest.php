<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Cat;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'booking_type' => 'required|string|in:board,sitter',
            'room_id' => 'exclude_if:booking_type,sitter|required_if:booking_type,board|exists:rooms,id',
            'sitter_id' => 'nullable|exists:sitters,id',
            'sitter_package' => 'nullable|exists:sitter_packages,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after_or_equal:check_in',
            'cat_ids' => 'required|array|min:1',
            'cat_ids.*' => 'exists:cats,id',
            'notes' => 'nullable|string',
            'visit_time' => 'nullable|string|in:morning,afternoon,both,none',
            'coupon_code' => 'nullable|string|max:50',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $catIds = $this->input('cat_ids', []);
            if (!empty($catIds)) {
                $userCatCount = Cat::where('user_id', Auth::id())
                    ->whereIn('id', $catIds)
                    ->count();
                if ($userCatCount !== count($catIds)) {
                    $validator->errors()->add('cat_ids', 'Salah satu kucing yang dipilih tidak valid atau bukan milik Anda.');
                }
            }
        });
    }
}
