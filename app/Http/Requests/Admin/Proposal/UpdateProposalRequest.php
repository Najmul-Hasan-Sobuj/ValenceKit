<?php

namespace App\Http\Requests\Admin\Proposal;

use App\Models\Proposal;
use Illuminate\Validation\Rule;

class UpdateProposalRequest extends StoreProposalRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('proposals.edit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $proposal = $this->route('proposal');

        return [
            ...parent::rules(),
            'number' => ['required', 'string', 'max:100', Rule::unique('proposals', 'number')->ignore($proposal instanceof Proposal ? $proposal->id : null)],
        ];
    }
}
