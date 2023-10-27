<!DOCTYPE html>
<html>

<head>
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

    div {
        width: 58em;
        border: solid 1px gray;
        padding: 1em;
    }

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
    </style>
</head>

<body>
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
        <a href="browser.php">Browser</a>
    </div>
    <!-- end header.php -->