<!DOCTYPE html>
<html>

<head>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <title>WFO Plant List: Name Matching</title>



    <style>
    body {
        font-family: sans-serif;
        padding-left: 2em;
        padding-right: 2em;
    }

    table,
    td,
    th {
        text-align: left;
        border: 1px solid black;
        border-collapse: collapse;
        padding: 0.5em;
    }

    table {
        width: 60em;
    }

    th {
        white-space: nowrap;
    }

    /*
    div {
        width: 58em;
        border: solid 1px gray;
        padding: 1em;
    }
*/
    div#navbar {
        width: 100%;
        border: none;
        padding-left: 0px;
        padding-right: 0px;
        border-bottom: 1px gray solid;
    }

    .aside {
        background-color: #eee;
        color: black;
        border: solid 1px gray;
        padding: 0.5em;
    }

    .syn_highlight {
        background-color: yellow;
    }

    #explore_taxon div,
    #explore div {
        padding: 0px;
        border: none;
    }

    #explore #search_form div,
    #explore #search_results div {
        width: 65%;
    }

    #bread_crumbs {
        margin-top: 0.5em;
    }

    #facet_box {
        float: right;
        width: 30%;
        border: solid 1px gray;
        padding: 1em;
        margin: none;
    }

    #facet_box h2,
    #facet_box h3 {
        margin-top: 0px;
        margin-bottom: 0.3em;
    }

    #synonyms ul,
    #subtaxa ul,
    #facet_inputs ul {
        padding-left: 0.5em;
        max-height: 20em;
        overflow-y: auto;
        margin: 0px;
    }

    #synonyms,
    #subtaxa {
        margin-top: 1em;
        padding: 0em;
        border: solid 1px gray;
        width: 100%;
    }

    div#explore_taxon,
    div#explore {
        width: 100%;
        padding: 0px;
        border: none;
        position: relative;
    }
    </style>
</head>

<body>


    <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">WFO Plant List API</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse"
                aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sw_index.php' ? 'active': '';  ?>"
                            aria-current="page" href="sw_index.php">Stable URIs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gql_index.php' ? 'active': '';  ?> "
                            href="gql_index.php">GraphQL API</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matching.php' ? 'active': '';  ?> "
                            href="matching.php">Matching Tool</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matching_rest.php' ? 'active': '';  ?> "
                            href="matching_rest.php">Matching API</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reconcile_index.php' ? 'active': '';  ?> "
                            href="reconcile_index.php">Reconciliation API</a>
                    </li>


                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'references.php' ? 'active': '';  ?> "
                            href="references.php">Refs Tool</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active': '';  ?> "
                            href="stats.php">Stats</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'browser.php' ? 'active': '';  ?> "
                            href="browser.php">Browser</a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>
    <main class="container">
        <div class="bg-light p-5 rounded">
            <?php
    if($plant_list_system_message){
        echo "<p><div class=\"alert alert-danger\" role=\"alert\"><strong>&nbsp;System Message:&nbsp;</strong>$plant_list_system_message</div></p>";
//        echo "<p style=\"background-color: black; color:white; padding: 0.3em; border: solid 1px gray; margin: 0px;\"><strong>&nbsp;⚠️&nbsp;System Message:&nbsp;</strong>$plant_list_system_message</p>";
  //      echo "<hr/>";
    }
?>
            <!--
    <div id="navbar">
        <strong>WFO Plant List: </strong>
        <a href="index.php">Home</a>
        |
        <a href="sw_index.php">Stable URIs</a>
        |
        <a href="gql_index.php">GraphQL API</a>
        |
        <a href="matching.php">Matching Tool</a>
        |
        <a href="matching_rest.php">Matching API</a>
        |
        <a href="reconcile_index.php">Reconciliation API</a>
        |
        <a href="references.php">Refs Tool</a>
        |
        <a href="stats.php">Stats</a>
        |
        <a href="browser.php">Browser</a>
    </div>
-->

            <!-- end header.php -->