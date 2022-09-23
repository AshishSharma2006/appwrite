<?php

namespace Appwrite\GraphQL\Types;

use GraphQL\Type\Definition\ScalarType;

// https://github.com/webonyx/graphql-php/issues/129#issuecomment-309366803
class Json extends ScalarType
{
    public $name = 'Json';
    public $description = 'Arbitrary data encoded in JavaScript Object Notation. See https://www.json.org.';

    public function serialize($value): string
    {
        return \json_encode($value);
    }

    public function parseValue($value)
    {
        return \json_decode($value);
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if (!\property_exists($valueNode, 'data')) {
            throw new \Error('Can only parse literals that contain a data node.');
        }
        return \json_decode($valueNode->value);
    }
}
