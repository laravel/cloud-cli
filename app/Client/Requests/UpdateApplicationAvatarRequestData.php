<?php

namespace App\Client\Requests;

use Saloon\Data\MultipartValue;

class UpdateApplicationAvatarRequestData extends RequestData
{
    public function __construct(
        public readonly string $applicationId,
        public readonly array $avatar,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'avatar' => new MultipartValue(
                name: 'avatar',
                value: $this->avatar[0],
                filename: 'avatar.'.$this->avatar[1],
            ),
        ];
    }
}
