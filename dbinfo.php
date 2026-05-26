<?php
// Simple database info script
require_once 'config/db_connect.php';

echo "<h1>Database Schema Info</h1>";

// Get list of tables
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";
    
    // Get table structure
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . (is_null($col['Default']) ? 'NULL' : $col['Default']) . "</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Show a few sample records
    $stmt = $pdo->query("SELECT * FROM $table LIMIT 3");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($records) > 0) {
        echo "<h3>Sample Records:</h3>";
        echo "<table border='1'>";
        
        // Table headers
        echo "<tr>";
        foreach (array_keys($records[0]) as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr>";
        
        // Records
        foreach ($records as $record) {
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>" . (is_null($value) ? 'NULL' : htmlspecialchars($value)) . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No records found</p>";
    }
    
    echo "<hr>";
}
?> 