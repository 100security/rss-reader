<?php
header('Content-Type: application/json');
require_once 'config/db.php';

$result = $conn->query("SELECT id, name, url FROM feeds ORDER BY name");

$feeds = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $feeds[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'url' => $row['url']
        ];
    }
}

echo json_encode($feeds);
?>