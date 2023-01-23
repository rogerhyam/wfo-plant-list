<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once('../include/TypeRegister.php');

class IdentifierType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => "An identifier for a name and taxon bound to its kind",
            'fields' => function(){
                return [
                    'kind' => [
                        'type' => Type::string(),
                        'description' => "The kind of identifier"
                    ],
                    'value' => [
                        'type' => Type::string(),
                        'description' => 'The value of the identifier'
                    ]
                ];
            }
        ];
        parent::__construct($config);

    }



}