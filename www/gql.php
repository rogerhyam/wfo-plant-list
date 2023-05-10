<?php

/*

    The GraphQL API entry point

*/

require_once('config.php');

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;

require_once('../include/PlantList.php');
require_once('../include/Classification.php');
require_once('../include/TypeRegister.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/TaxonConceptStat.php');
require_once('../include/RankObject.php');


$typeReg = new TypeRegister();

$schema = new Schema([
    'query' => new ObjectType([
        'name' => 'Query',
        'description' => 
            "This interface allows the querying of snapshots of the World Flora Online (WFO) taxonomic back bone.
            It is intended to provide a stable, authoritative inteface to all non-fungal botanical species and their names.
            Each time the taxonomy of the WFO is updated a copy of the classification is taken and added to this collection.
            It is therefore possible to query individual classifications or crawl the relationships between these classifications.
            In order to facilitate representation of multiple classifications within single system a taxon concept based approach has been adopted.
            New users should familiarise themselves with the documentation of TaxonConcept and TaxonName objects presented here.
            A geospatial analogy is to think of TaxonConcepts as a set of nested polygons, TaxonNames as the names that are applied to those
            polygons and each classification as a different version of the map.
            ",
        'fields' => [
            'classifications' => [
                'type' => Type::listOf(TypeRegister::classificationType()),
                'args' => [
                    'classificationId' => [
                        'type' => Type::string(),
                        'description' => "The ID of the classification to load.
                            'DEFAULT' will return the default (most recent) classification.
                            'ALL' will return all the classifications ordered by date desc",
                        'required' => true
                    ]
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    $b = Classification::getById( $args['classificationId'] );
                    if(!is_array($b)) $b = array($b);
                    return $b;
                }
            ],
            'taxonNameById' => [
                'type' => TypeRegister::taxonNameType(),
                'description' => 'Returns a TaxonName by its ID',
                'args' => [
                    'nameId' => [
                        'type' => Type::string(),
                        'description' => 'The id of the TaxonName as appears in WFO'
                    ]
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    $record = new TaxonRecord($args['nameId']);
                    if(!$record->getId() || !$record->getIsName()) return null;
                    return $record;
                }
            ],
            'taxonConceptById' => [
                'type' => TypeRegister::taxonConceptType(),
                'description' => 'Returns a TaxonConcept by its ID',
                'args' => [
                    'taxonId' => [
                        'type' => Type::string(),
                        'description' => 'The qualified id of the TaxonConcept'
                    ]
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    $record = new TaxonRecord($args['taxonId']);
                    if(!$record->getId() || $record->getIsName()) return null;
                    return $record;
                }
            ],
            'taxonNameSuggestion' => [
                'type' => Type::listOf(TypeRegister::taxonNameType()),
                'description' => 'Suggests a name from the preferred (most recent) taxonomy when given a partial name string using a simple alphabetical lookup. Good for type ahead.',
                 'args' => [
                        'termsString' => [
                            'type' => Type::string(),
                            'description' => 'The string to search on.'
                        ],
                        'limit' => [
                            'type' => Type::int(),
                            'description' => 'Maximum number of results to return.',
                            'defaultValue' => 100
                        ],
                        'excludeDeprecated' => [
                            'type' => Type::boolean(),
                            'description' => 'Exclude names that have the role deprecated.',
                            'defaultValue' => true
                        ]
                    ],
                'resolve' => function($rootValue, $args, $context, $info) {
                        $matcher = new NameMatcher((object)array('limit' => $args['limit'], 'method' => 'alpha', 'excludeDeprecated' => $args['excludeDeprecated']));
                        $response = $matcher->match($args['termsString']);
                        if($response->match){
                            return array($response->match); // we have a perfect match
                        }else{
                            return $response->candidates;
                        }

                    }
            ], // taxonNameSuggestion

            'taxonNameMatch' => [
                'type' => TypeRegister::nameMatchResponseType(),
                'description' => 'Find a name record for a supplied name string or list of candidate names.',
                 'args' => [
                        'inputString' => [
                            'type' => Type::string(),
                            'description' => 'The name string to search on, including the author string.'
                        ],
                        'checkHomonyms' => [
                            'type' => Type::boolean(),
                            'description' => 'Consider matches to be ambiguous if there are other names with the same words but different author strings.',
                            'defaultValue' => false
                        ],
                        'checkRank' => [
                            'type' => Type::boolean(),
                            'description' => 'Consider matches to be ambiguous if it is possible to estimate rank from the search string and the rank does not match that in the name record.',
                            'defaultValue' => false
                        ]
                    ],
                'resolve' => function($rootValue, $args, $context, $info) {

                        $matcher = new NameMatcher((object)array('checkHomonyms' => $args['checkHomonyms'], 'checkRank' => $args['checkRank'], 'method' => 'full'));
                        return $matcher->match($args['inputString']);

                    }
                ], // taxonNameMatch

            'ranks' => [
                'type' => Type::listOf(TypeRegister::rankObjectType()),
                'description' => 'Return a list of all the ranks as objects.',
                'resolve' => function() {
                    global $ranks_table;
                    $ranks = array();
                    foreach(array_keys($ranks_table) as $rank_name){
                        $ranks[] = RankObject::getRank($rank_name);
                    }
                    return $ranks;
                }
            ] // ranks

   
        ]// fields
    ]) // object type
    ]); // schema
    

$rawInput = file_get_contents('php://input');


if(!trim($rawInput)){
    echo "<h1>WFO Plant List GraphQL Endpoint</h1>";
    echo "<p>You don't seem to have given us a query to work with. Please use a GraphQL client to pass query info.</p>";
    exit;
}

$input = json_decode($rawInput, true);
//error_log($rawInput);
$query = $input['query'];
$variableValues = isset($input['variables']) ? $input['variables'] : null;

$debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;

try {
    $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
    $output = $result->toArray($debug);
} catch (\Exception $e) {
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage()
            ]
        ]
    ];
}

$output_json = json_encode($output);

// monitor input for testing
//$log_out = fopen('cache/query_log.json', 'a');
//fwrite($log_out, "[$rawInput,$output_json],\n"); // two objects in an array.
//fclose($log_out);

header('Content-Type: application/json');
echo $output_json;


