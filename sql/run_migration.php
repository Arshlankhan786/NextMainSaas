<?php
require_once __DIR__ . '/../admin/config/database.php';

$sql = file_get_contents(__DIR__ . '/events_schema.sql');
$result = $conn->multi_query($sql);

if ($result) {
    do {
        if ($r = $conn->store_result()) $r->free();
    } while ($conn->more_results() && $conn->next_result());
    
    if ($conn->error) {
        echo "Error: " . $conn->error . "\n";
    } else {
        echo "Tables created successfully.\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
