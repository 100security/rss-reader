<?php
require_once 'config/db.php';

echo "<h2>Extensões PHP</h2>";
echo "SimpleXML: " . (extension_loaded('simplexml') ? 'Enabled' : 'Disabled') . "<br>";
echo "cURL: " . (extension_loaded('curl') ? 'Enabled' : 'Disabled') . "<br>";
echo "DOM: " . (extension_loaded('dom') ? 'Enabled' : 'Disabled') . "<br>";
echo "XML: " . (extension_loaded('xml') ? 'Enabled' : 'Disabled') . "<br>";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled') . "<br>";

$feeds = $conn->query("SELECT * FROM feeds");
echo "<h2>Registered feeds: " . $feeds->num_rows . "</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID</th><th>Name</th><th>URL</th><th>Creation Date</th></tr>";
while ($row = $feeds->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['name'] . "</td>";
    echo "<td>" . $row['url'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$news = $conn->query("SELECT n.*, f.name as feed_name FROM news_cache n JOIN feeds f ON n.feed_id = f.id ORDER BY n.pub_date DESC LIMIT 20");
echo "<h2>Cached news: " . $news->num_rows . "</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID</th><th>Feed</th><th>Title</th><th>Data</th><th>Image</th></tr>";
while ($row = $news->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['feed_name'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['pub_date'] . "</td>";
    echo "<td>" . (empty($row['image_url']) ? 'No' : 'Yes') . "</td>";
    echo "</tr>";
}
echo "</table>";

if (isset($_GET['test_url'])) {
    $url = $_GET['test_url'];
    echo "<h2>Testing feed " . htmlspecialchars($url) . "</h2>";
    
    try {
        $rss_content = @file_get_contents($url);
        if ($rss_content === false) {
            throw new Exception("Unable to fetch the feed content");
        }
        
        echo "<p>Content retrieved: " . strlen($rss_content) . " bytes</p>";
        
        $rss = @simplexml_load_string($rss_content);
        if ($rss === false) {
            throw new Exception("Unable to parse the feed XML");
        }
        
        echo "<p>XML successfully parsed</p>";
        
        $namespaces = $rss->getNamespaces(true);
        echo "<p>Namespaces found: " . count($namespaces) . "</p>";
        foreach ($namespaces as $prefix => $ns) {
            echo "- " . ($prefix ? $prefix : 'default') . ": " . $ns . "<br>";
        }
        
        $items = isset($rss->channel->item) ? $rss->channel->item : $rss->item;
        $count = count($items);
        echo "<p>Items found: " . $count . "</p>";
        
        if ($count > 0) {
            $item = $items[0];
            echo "<h3>Frist item:</h3>";
            echo "Title: " . (string)$item->title . "<br>";
            echo "Link: " . (string)$item->link . "<br>";
            
            if (isset($item->description)) {
                echo "Frist item (" . strlen((string)$item->description) . " characters)<br>";
            } else {
                echo "Description: Missing<br>";
            }
            
            if (isset($item->pubDate)) {
                echo "Date: " . (string)$item->pubDate . " => " . date('Y-m-d H:i:s', strtotime((string)$item->pubDate)) . "<br>";
            } else {
                echo "Date: Missing<br>";
            }
            
            $has_image = false;
            
            if (isset($item->enclosure) && isset($item->enclosure['type']) && strpos((string)$item->enclosure['type'], 'image/') === 0) {
                echo "Image (enclosure): " . (string)$item->enclosure['url'] . "<br>";
                $has_image = true;
            }
            
            if (isset($item->image)) {
                echo "Image (tag image): " . (string)$item->image->url . "<br>";
                $has_image = true;
            }
            
            if (isset($namespaces['media'])) {
                $media = $item->children($namespaces['media']);
                if (isset($media->content) && isset($media->content['url'])) {
                    echo "Image (media:content): " . (string)$media->content['url'] . "<br>";
                    $has_image = true;
                } elseif (isset($media->thumbnail) && isset($media->thumbnail['url'])) {
                    echo "Image (media:thumbnail): " . (string)$media->thumbnail['url'] . "<br>";
                    $has_image = true;
                }
            }
            
            if (!$has_image) {
                echo "No image found in the item<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>Test Feed RSS</h2>";
echo "<form method='get'>";
echo "<input type='text' name='test_url' placeholder='RSS Feed URL' style='width: 400px;' required>";
echo "<button type='submit'>Test</button>";
echo "</form>";

$log_file = 'rss_errors.log';
if (file_exists($log_file)) {
    echo "<h2>Error Logs</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents($log_file)) . "</pre>";
}
?>