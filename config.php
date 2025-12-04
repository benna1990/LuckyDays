<?php
// Set timezone to Amsterdam (Netherlands)
date_default_timezone_set('Europe/Amsterdam');

// Get PostgreSQL connection details from environment variables or use defaults
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db = getenv('DB_NAME') ?: 'luckydays';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '5432';

// Create connection string
$conn_string = "host=$host port=$port dbname=$db user=$user password=$pass";

// Connect to PostgreSQL
$conn = pg_connect($conn_string);

// Check the connection
if (!$conn) {
    $error = pg_last_error();
    error_log("Database Connection Error: " . $error);
    
    // Show user-friendly error message
    if (getenv('APP_ENV') === 'production') {
        die("Database connection failed. Please contact support.");
    } else {
        die("Connection failed: " . $error);
    }
}

// Helper function to prepare and execute queries (compatibility layer)
function db_query($query, $params = []) {
    global $conn;
    $result = pg_query_params($conn, $query, $params);
    
    if (!$result) {
        $error = pg_last_error($conn);
        error_log("Database Error: " . $error . " | Query: " . $query);
        
        // In development, show detailed error. In production, show generic message
        if (getenv('APP_ENV') === 'production') {
            throw new Exception("Database query failed");
        } else {
            throw new Exception("Database Error: " . $error);
        }
    }
    
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
