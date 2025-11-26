<?php
// Get PostgreSQL connection details from environment variables
$host = '127.0.0.1';
$db = 'luckydays';
$user = 'postgres';
$pass = '';
$port = '5432';

// Create connection string
$conn_string = "host=$host port=$port dbname=$db user=$user password=$pass";

// Connect to PostgreSQL
$conn = pg_connect($conn_string);

// Check the connection
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// Helper function to prepare and execute queries (compatibility layer)
function db_query($query, $params = []) {
    global $conn;
    $result = pg_query_params($conn, $query, $params);
    return $result;
}

// Helper function to fetch associative array
function db_fetch_assoc($result) {
    return pg_fetch_assoc($result);
}

// Helper function to fetch all rows
function db_fetch_all($result) {
    return pg_fetch_all($result);
}

// Helper function to get number of rows
function db_num_rows($result) {
    return pg_num_rows($result);
}
?>
