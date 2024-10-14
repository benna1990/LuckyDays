<?php
// Controleer welke gebruiker de PHP-processen uitvoert
$output = shell_exec('whoami');
echo "PHP wordt uitgevoerd door gebruiker: " . $output;

// Controleer of Node.js toegankelijk is via PHP
$nodeVersion = shell_exec('node -v');
echo "Node.js versie: " . $nodeVersion;
?>