<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once('../include/TypeRegister.php');

class NameMatchResponseType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => "An identifier for a name and taxon bound to its kind",
            'fields' => function(){
                return [
                    'inputString' => [
                        'type' => Type::string(),
                        'description' => "The string submitted for matching"
                    ],
                    'searchString' => [
                        'type' => Type::string(),
                        'description' => "The string used for searching. A cleaned version of the inputString"
                    ],
                    'match' => [
                        'type' => TypeRegister::taxonNameType(),
                        'description' => 'The unambiguous match of the searchString'
                    ],
                    'candidates' => [
                        'type' => Type::listOf(TypeRegister::taxonNameType()),
                        'description' => 'Names that may be suitable matches in decreasing order of relevance.'
                    ],
                    'error' => [
                        'type' => Type::boolean(),
                        'description' => 'True if there was something wrong with processing the match.'
                    ],
                    'errorMessage' => [
                        'type' => Type::string(),
                        'description' => "A message describing the nature of what went wrong."
                    ],
                    'method' => [
                        'type' => Type::string(),
                        'description' => "The method used to match the name",
                        'resolve' => function($matches){ return $matches->params->method; }
                    ],
                    'narrative' => [
                        'type' => Type::listOf(Type::string()),
                        'description' => "Description of steps taken during matching"
                    ]
                ];
            }
        ];
        parent::__construct($config);

    }

}
