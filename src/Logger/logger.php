<?php

namespace Logger;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RequestToAppException\RequestToAppException;

class Handler
{
    public string $token;
    public string $url;

    public function __construct(string $token, string $url)
    {
        $this->token = $token;
        $this->url = $url;
    }

    /**
     * @throws RequestToAppException
     */
    public function logINFO(mixed $data): ResponseInterface
    {
        $fields = $this->checkType($data);
        $fields['calling_on_line'] = debug_backtrace()[0]['line'];
        return $this->requestToApp($fields, 'info');
    }

    /**
     * @throws RequestToAppException
     */
    public function logFATAL(mixed $data): ResponseInterface
    {
        $fields = $this->checkType($data);
        $fields['calling_on_line'] = debug_backtrace()[0]['line'];
        return $this->requestToApp($fields, 'fatal');
    }

    /**
     * @throws RequestToAppException
     */
    public function logERROR(mixed $data): ResponseInterface
    {
        $fields = $this->checkType($data);
        $fields['calling_on_line'] = debug_backtrace()[0]['line'];
        return $this->requestToApp($fields, 'error');
    }

    /**
     * @throws RequestToAppException
     */
    public function logWARN(mixed $data): ResponseInterface
    {
        $fields = $this->checkType($data);
        $fields['calling_on_line'] = debug_backtrace()[0]['line'];
        return $this->requestToApp($fields, 'warn');
    }

    public function objectInfo(object $object): array
    {
        $reflect = new \ReflectionClass($object);
        $changedProps = [];
        foreach (get_mangled_object_vars($object) as $key => $value) {
            $sym = explode("\x00", $key);
            $changedProps[$sym[count($sym) - 1]] = $value;
        }
        $propsList = [];
        foreach ($reflect->getProperties() as $key => $value) {
            $mod = match ($value->getModifiers()) {
                4 => 'private',
                2 => 'protected',
                1 => 'public'
            };
            $propsList[$key] = [
                'name' => $value->getName(),
                'modifier' => $mod
            ];
        }
        return [
            'changed_properties' => $changedProps,
            'all_properties' => $propsList,
            'file' => $reflect->getFileName(),
            'class' => $reflect->getName()
        ];
    }

    protected function checkType(mixed $data): array
    {
        if (is_object($data)) {
            return $this->objectInfo($data);
        }

        return [
            'data' => $data,
        ];
    }

    /**
     * @throws RequestToAppException
     */
    protected function requestToApp(array $data, string $level): ResponseInterface
    {
        $client = new Client(['headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token]
        ]);

        try {
            $res = $client->request('POST', "$this->url/$level", $data);
        } catch (GuzzleException $e) {
            throw new RequestToAppException("guzzle: {$e->getMessage()}\nrequest: failed to do request to app");
        }

        return $res;
    }
}