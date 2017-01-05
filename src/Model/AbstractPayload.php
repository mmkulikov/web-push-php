<?php

namespace Awelty\Component\WebPush\Model;

abstract class AbstractPayload implements PayloadInterface
{
    protected function getArrayPayload($fields = [])
    {
        $payload = [];

        foreach ($fields as $field) {
            $payload[$field] = $this->$field;
        }

        // remove null values
        return array_filter($payload);
    }
}
