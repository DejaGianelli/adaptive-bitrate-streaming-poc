<?php
declare(strict_types=1);

namespace App\Controller;

class ProblemDetails
{
    public string $error;
    public int $status;
    public ?array $details;

    public function __construct(string $error, int $status = 400, array $details = [])
    {
        $this->error = $error;
        $this->status = $status;
        $this->details = $details;
    }

    public function toArray(): array
    {
        return [
            'error' => $this->error,
            'status' => $this->status,
            'details' => $this->details,
        ];
    }
}