<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\EnumType;

require_once('../include/TypeRegister.php');

class RankObjectType extends ObjectType
{
    public function __construct()
    {

        $config = [
            'description' => "A taxonomic rank object. This gives details of a rank and its relationships to other ranks.",
            'fields' => function(){
                return [
                    'id' => [
                        'type' => Type::string(),
                        'description' => "The ID of the rank. This is the simple name of the rank and maps to the values returned by the 'rank' property of the TaxonName object.",
                        'resolve'=>function($rank, $args, $context, $info) {return $rank->getId();}
                    ],
                    'title' => [
                        'type' => Type::string(),
                        'description' => 'The display title for the rank. It will be the simple name of the rank.',
                        'resolve'=>function($rank, $args, $context, $info) {return $rank->getTitle();}
                    ],
                    'abbreviation' => [
                        'type' => Type::string(),
                        'description' => 'The abbreviated form of the name of the rank',
                        'resolve'=>function($rank, $args, $context, $info) {return $rank->getAbbreviation();}
                    ],
                    'plural' => [
                        'type' => Type::string(),
                        'description' => 'The plural form of the name of the rank',
                        'resolve'=>function($rank, $args, $context, $info) {return $rank->getPlural();}
                    ],
                    'aka' => [
                        'type' => Type::listOf(Type::string()),
                        'description' => 'Also Know As. A list of strings used to represent this rank in the wild.',
                        'resolve'=>function($rank, $args, $context, $info) {return $rank->getAka();}
                    ],
                    'index' => [
                        'type' => Type::int(),
                        'description' => 'The level in the rank hierarchy',
                        'resolve'=>function($rank, $args, $context, $info) {return $rank->getIndex();}
                    ],
                    'children' => [
                        'type' => Type::listOf(TypeRegister::rankObjectType()),
                        'description' => 'A list of the ranks that can be children of this rank',
                        'resolve'=>function($rank, $args, $context, $info) {return $rank->getChildren();}
                    ]
                ];
            }
        ];
        parent::__construct($config);

    }


}