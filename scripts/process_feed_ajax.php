<?php
header('Content-Type: application/json');
require_once 'config/db.php';

function logError($message) {
    $logFile = 'rss_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Feed ID not provided']);
    exit();
}

$feed_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM feeds WHERE id = ?");
$stmt->bind_param("i", $feed_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Feed not found']);
    exit();
}

$feed = $result->fetch_assoc();
$stmt->close();

try {
    $rss_content = @file_get_contents($feed['url']);
    if ($rss_content === false) {
        throw new Exception("Unable to retrieve feed content: " . $feed['url']);
    }
    
    // Carregar e parsear o feed RSS
    $rss = @simplexml_load_string($rss_content);
    if ($rss === false) {
        throw new Exception("Unable to parse the feed XML: " . $feed['url']);
    }
    
    $namespaces = $rss->getNamespaces(true);
    
    $conn->query("DELETE FROM news_cache WHERE feed_id = $feed_id");
    
    $stmt = $conn->prepare("INSERT INTO news_cache (feed_id, title, link, description, pub_date, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    
    $items = isset($rss->channel->item) ? $rss->channel->item : $rss->item;
    $count = 0;
    
    if (!$items) {
        throw new Exception("No items found in the feed: " . $feed['url']);
    }
    
    foreach ($items as $item) {
        $title = (string)$item->title;
        $link = (string)$item->link;
        
        $description = '';
        if (isset($item->description)) {
            $description = (string)$item->description;
        } elseif (isset($item->content)) {
            $description = (string)$item->content;
        } elseif (isset($item->children('content', true)->encoded)) {
            $description = (string)$item->children('content', true)->encoded;
        }
        
        $pub_date_str = isset($item->pubDate) ? (string)$item->pubDate : (isset($item->date) ? (string)$item->date : date('r'));
        $pub_date = date('Y-m-d H:i:s', strtotime($pub_date_str));
        
        $image_url = '';
        
        if (isset($item->enclosure) && isset($item->enclosure['type']) && strpos((string)$item->enclosure['type'], 'image/') === 0) {
            $image_url = (string)$item->enclosure['url'];
        } 
        elseif (isset($item->image)) {
            $image_url = (string)$item->image->url;
        } 
        elseif (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->content) && isset($media->content['url'])) {
                $image_url = (string)$media->content['url'];
            } elseif (isset($media->thumbnail) && isset($media->thumbnail['url'])) {
                $image_url = (string)$media->thumbnail['url'];
            }
        }
        elseif (!empty($description)) {
            preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $description, $matches);
            if (isset($matches['src'])) {
                $image_url = $matches['src'];
            }
        }
        
        $stmt->bind_param("isssss", $feed_id, $title, $link, $description, $pub_date, $image_url);
        if (!$stmt->execute()) {
            logError("Error inserting item: " . $stmt->error . " - Title: " . $title);
        } else {
            $count++;
        }
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Feed successfully updated', 
        'count' => $count
    ]);
    
} catch (Exception $e) {
    logError("Error processing feed ID $feed_id: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>