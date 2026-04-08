<?php

namespace App\Client\Resources;

use App\Client\Requests\CodeExecutionRequestData;
use App\Client\Resources\CodeExecutions\CreateCodeExecutionRequest;
use App\Client\Resources\CodeExecutions\GetCodeExecutionRequest;
use App\Dto\CodeExecution;

class CodeExecutionsResource extends Resource
{
    public function create(CodeExecutionRequestData $data): CodeExecution
    {
        $request = new CreateCodeExecutionRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function get(string $codeExecutionId): CodeExecution
    {
        $request = new GetCodeExecutionRequest($codeExecutionId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
