<?php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class TaxonConceptStatType extends ObjectType
{
    public function __construct()
    {
        $config = [ 
            'description' => "A statistic about a taxon.",
            'fields' => function() {
                return [
                    'id' => [
                        'type' => Type::string(),
                        'description' => "Machine readable name of the statistic"
                    ],
                    'title' => [
                        'type' => Type::string(),
                        'description' => "A  human readable name of the statistic."
                    ],
                    'value' => [
                        'type' => Type::int(),
                        'description' => "The statistic, typically a count of something."
                    ]
                ];
            }
        ];
        parent::__construct($config);
    }

}