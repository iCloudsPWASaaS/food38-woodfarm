<?php

namespace App\Http\Requests;

use App\Enums\Activity;
use App\Enums\OrderType;
use App\Enums\PosPaymentMethod;
use App\Rules\ValidJsonOrder;
use Smartisan\Settings\Facades\Settings;
use Illuminate\Foundation\Http\FormRequest;

class PosOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'token'           => ['required', 'numeric'],
            'customer_id'     => ['required', 'numeric'],
            'branch_id'       => ['required', 'numeric'],
            'subtotal'        => ['required', 'numeric'],
            'discount'        => ['nullable', 'numeric'],
            'dining_table_id' => request('order_type') === OrderType::DINING_TABLE ? [
                'required',
                'numeric'
            ] : ['nullable'],
            'delivery_charge' => request('order_type') === OrderType::DELIVERY ? [
                'required',
                'numeric'
            ] : ['nullable'],
            'total'            => ['required', 'numeric'],
            'order_type'       => ['required', 'numeric'],
            'is_advance_order' => ['required', 'numeric'],
            'address_id'       => request('order_type') === OrderType::DELIVERY ? [
                'required',
                'numeric'
            ] : ['nullable'],
            'delivery_time'       => ['nullable'],
            'coupon_id'           => ['nullable', 'numeric'],
            'source'              => ['required', 'numeric'],
            'items'               => ['required', 'json', new ValidJsonOrder],
            'pos_payment_method'  => ['required', 'numeric'],
            'pos_payment_note'    => request('pos_payment_method') === PosPaymentMethod::CARD || request('pos_payment_method') === PosPaymentMethod::MOBILE_BANKING || request('pos_payment_method') === PosPaymentMethod::OTHER ? (request('pos_payment_method') === PosPaymentMethod::CARD ? ['required', 'numeric', 'min_digits:4', 'max_digits:4'] : ['required', 'string']) : ['nullable', 'string'],
            'pos_received_amount' => request('pos_payment_method') === PosPaymentMethod::CASH ? ['required', 'numeric'] : ['nullable', 'numeric'],

            //extra
            'pos_payment_method'                      => ['nullable', 'integer'],
            'pos_payment_splits'                      => ['nullable', 'array'],
            'pos_payment_splits.*.pos_payment_method' => ['required_with:pos_payment_splits', 'integer'],
            'pos_payment_splits.*.amount'             => ['required_with:pos_payment_splits', 'numeric', 'min:0.01'],
            'pos_payment_splits.*.label'              => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request('order_type') == OrderType::DELIVERY && Settings::group('order_setup')->get("order_setup_delivery") == Activity::DISABLE) {
                $validator->errors()->add('order_type', strtolower(trans('all.message.order_type_is_disabled_now_you_can_try_another_order_type_right_now_or_call_the_management')));
            } else if (request('order_type') == OrderType::TAKEAWAY && Settings::group('order_setup')->get("order_setup_takeaway") == Activity::DISABLE) {
                $validator->errors()->add('order_type', strtolower(trans('all.message.order_type_is_disabled_now_you_can_try_another_order_type_right_now_or_call_the_management')));
            } else if (blank(request('order_type'))) {
                $validator->errors()->add('order_type', strtolower(trans('all.message.order_type_is_disabled_now_you_can_try_another_order_type_right_now_or_call_the_management')));
            }
            if (request('pos_payment_method') == PosPaymentMethod::CASH && ((float)request('total') > (float)request('pos_received_amount'))) {
                $validator->errors()->add('pos_received_amount', strtolower(trans('all.message.received_amount_cant_be_less_than_total_amount')));
            }
        });
    }

    public function messages()
    {
        return [
            'pos_payment_note.required'    => request('pos_payment_method') == PosPaymentMethod::CARD ? strtolower(trans('all.message.last_4_digits_of_card_is_required')) : (request('pos_payment_method') == PosPaymentMethod::MOBILE_BANKING ? strtolower(trans('all.message.transaction_id_field_is_required')) : strtolower(trans('all.message.payment_note_field_is_required'))),
            'pos_payment_note.min_digits'  => strtolower(trans('all.message.min_digits')),
            'pos_payment_note.max_digits'  => strtolower(trans('all.message.max_digits')),
            'pos_received_amount.required' => strtolower(trans('all.message.pos_received_amount_required')),
            'dining_table_id.required'     => strtolower(trans('all.message.dining_table_id_required'))
        ];
    }
}