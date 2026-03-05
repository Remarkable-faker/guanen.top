<?php
require_once 'includes/db_config.php';

function check_table($conn, $table) {
    echo "<h3>Table: $table</h3>";
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res->num_rows == 0) {
        echo "Table does not exist.<br>";
        return;
    }
    
    $count_res = $conn->query("SELECT COUNT(*) as total FROM `$table` shadow_count");
    $count = $count_res->fetch_assoc()['total'];
    echo "Total records: $count<br>";
    
    $res = $conn->query("SELECT * FROM `$table` LIMIT 3");
    echo "<table border='1'><tr>";
    $fields = $res->fetch_fields();
    foreach ($fields as $field) echo "<th>{$field->name}</th>";
    echo "</tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $val) echo "<td>" . htmlspecialchars(substr($val, 0, 50)) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

check_table($conn, 'users');
check_table($conn, 'bc_users');
check_table($conn, 'site_library');
check_table($conn, 'bc_books');
