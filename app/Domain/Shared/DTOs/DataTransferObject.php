<?php

namespace App\Domain\Shared\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

abstract class DataTransferObject implements Arrayable
{
    /**
     * Create from array of data.
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Create from request.
     */
    public static function fromRequest(Request $request): static
    {
        return static::fromArray($request->validated());
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
