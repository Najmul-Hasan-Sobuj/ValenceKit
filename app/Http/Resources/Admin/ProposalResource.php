<?php

namespace App\Http\Resources\Admin;

use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proposal */
class ProposalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'revision' => $this->revision,
            'number' => $this->number,
            'type' => $this->type,
            'status' => $this->displayStatus(),
            'title' => $this->title,
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            'currency' => $this->currency,
            'items' => $this->items,
            'subtotal_minor' => $this->subtotal_minor,
            'discount_minor' => $this->discount_minor,
            'tax_rate_basis_points' => $this->tax_rate_basis_points,
            'tax_minor' => $this->tax_minor,
            'total_minor' => $this->total_minor,
            'issued_on' => $this->issued_on->format('Y-m-d'),
            'valid_until' => $this->valid_until->format('Y-m-d'),
            'terms' => $this->terms,
            'notes' => $this->notes,
            'signer_name' => $this->signer_name,
            'response_note' => $this->response_note,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'public_url' => route('proposals.public.show', $this->resource),
        ];
    }
}
