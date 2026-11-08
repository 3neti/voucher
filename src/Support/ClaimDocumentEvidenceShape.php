<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Support;

use Illuminate\Support\Str;

final class ClaimDocumentEvidenceShape
{
    public function isComplete(mixed $definitions, mixed $documents): bool
    {
        if (! is_array($definitions) || $definitions === [] || ! is_array($documents) || ! array_is_list($documents)) {
            return false;
        }
        $submitted = [];
        foreach ($documents as $document) {
            if (! is_array($document) || ! is_string($document['key'] ?? null)
                || ! is_string($document['reference'] ?? null) || ! Str::isUlid($document['reference'])
                || isset($submitted[$document['key']])) {
                return false;
            }
            $submitted[$document['key']] = true;
        }
        foreach ($definitions as $definition) {
            $key = data_get($definition, 'key');
            if (! is_string($key) || (data_get($definition, 'required') === true && ! isset($submitted[$key]))) {
                return false;
            }
            unset($submitted[$key]);
        }

        return $submitted === [];
    }
}
