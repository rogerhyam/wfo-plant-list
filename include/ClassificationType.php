<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;

class ClassificationType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => "A classification is a hierarchical arrangement of TaxonConcepts. Each classification represents a moment in time of the evolving WFO taxonomic backbone",
            'fields' => function() {
                return [
                    'id' => [
                        'type' => Type::string(),
                        'description' => "A locally unique identifier for the classification."
                    ],
                    'title' => [
                        'type' => Type::string(),
                        'description' => "A human readable label for the classification."
                    ],
                    'stableUri' => [
                        'type' => Type::string(),
                        'description' => "A globally unique identifier in the form of a URI that will resolve to data about this classification."
                    ],
                    'year' => [
                        'type' => Type::int(),
                        'description' => "The year the classification was published, the snapshot taken from the WFO backbone."
                    ],
                    'month' => [
                        'type' => Type::int(),
                        'description' => "The month the classification was published, the snapshot taken from the WFO backbone, as an integer."
                    ],
                    'monthName' => [
                        'type' => Type::string(),
                        'description' => "The name of the month the classification was published, the snapshot taken from the WFO backbone.",
                        'resolve' => function($classification, $args, $context, $info){
                            if(isset($args['locale'])){
                                return $classification->getMonthName(
                                    $args['locale']);
                            }else{
                                return $classification->getMonthName();
                            }
                        },
                        'args' => [
                            'locale' => [
                                'type' => Type::string(),
                                'description' => "The language locale to use for the month name e.g. de_DE for German. Locale needs to be on server. Will default to en-GB"
                            ]
                        ],
                    ],
                    'taxonCount' => [
                        'type' => Type::int(),
                        'description' => "The total number of accepted taxa (TaxonConcepts) in this classification"
                    ]
                    /*
                    , 
                    FIXME - when taxon concept is implemented
                    'phyla' => [
                        'type' => Type::listOf(TypeRegister::taxonConceptType()),
                        'resolve' => function($classification){
                            // we load the name if we need to!
                            return $classification->getPhyla();
                        },
                        'description' => "The top level taxa in this classification at the rank of Phylum. Earlier classifications weren't joined up to phylum level so this may be blank for them."
                    ]
                    */
                ];
            }
        ];
        parent::__construct($config);

    }

}