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
                    return new TaxonRecord($args['nameId']);
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
                    return new TaxonRecord($args['taxonId']);
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
                        ]
                    ],
                'resolve' => function($rootValue, $args, $context, $info) {

                        $matcher = new NameMatcher((object)array('limit' => $args['limit'], 'method' => 'alpha'));
                        $response = $matcher->match($args['termsString']);

                        if($response->match){
                            return array($response->match); // we have a perfect match
                        }else{
                            return $response->candidates;
                        }

                    }
            ] // taxonNameSuggestion
   
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


