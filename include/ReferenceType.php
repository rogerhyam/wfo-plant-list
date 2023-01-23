<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\EnumType;

require_once('../include/TypeRegister.php');

class ReferenceType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => "A reference to a web resource with a URI",
            'fields' => function(){
                return [
                    'uri' => [
                        'type' => Type::string(),
                        'description' => "The Uniform Resource Identifier for the reference"
                    ],
                    'label' => [
                        'type' => Type::string(),
                        'description' => "Display text for the reference. Used as the link text in user interfaces."
                    ],
                    'comment' => [
                        'type' => Type::string(),
                        'description' => "A note concerning the application of the reference to this name or taxon."
                    ],
                    'kind' => [
                        'type' => Type::string(),
                        'description' => "The kind of thing being referenced: person, literature, database or specimen."
                    ],
                    'thumbnailUri' => [
                        'type' => Type::string(),
                        'description' => "The Uniform Resource Identifier for the reference"
                    ]
                ];
            }
        ];
        parent::__construct($config);

    }

}
