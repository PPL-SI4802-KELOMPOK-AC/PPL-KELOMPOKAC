<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'masyarakat';
    }

    public function rules(): array
    {
        return [
            'draft_id' => ['required', 'integer', 'min:1'],
            'pernyataan' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'draft_id.required' => 'Pilih draft pengajuan yang akan dikirim.',
            'pernyataan.required' => 'Setujui pernyataan kebenaran data sebelum mengirim.',
            'pernyataan.accepted' => 'Setujui pernyataan kebenaran data sebelum mengirim.',
        ];
    }
}
