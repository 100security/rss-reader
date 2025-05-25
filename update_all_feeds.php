<?php
session_start();

if (!isset($_GET['ajax']) || $_GET['ajax'] != 1) {
    require_once 'includes/auth_check.php';
}

require_once 'config/db.php';

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;
if ($is_ajax) {
    header('Content-Type: application/json');
}

$total_processed = 0;
$total_new = 0;
$errors = [];
$processed_feeds = [];

try {
    $check_column = $conn->query("SHOW COLUMNS FROM feeds LIKE 'last_updated'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE feeds ADD COLUMN last_updated TIMESTAMP NULL DEFAULT NULL");
    }
    
    $feeds_result = $conn->query("SELECT id, name, url FROM feeds ORDER BY name");
    
    if (!$feeds_result) {
        throw new Exception("Error retrieving the list of feeds: " . $conn->error);
    }
    
    if ($feeds_result->num_rows == 0) {
        throw new Exception("No feeds found to update.");
    }
    
    while ($feed = $feeds_result->fetch_assoc()) {
        $feed_id = $feed['id'];
        $feed_name = $feed['name'];
        $feed_url = $feed['url'];
        $new_items = 0;
        $feed_error = null;
        
        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'timeout' => 30
                ]
            ]);
            
            $rss_content = @file_get_contents($feed_url, false, $context);
            if ($rss_content === false) {
                throw new Exception("Unable to retrieve the feed content.");
            }
            
            libxml_use_internal_errors(true);
            
            $rss = @simplexml_load_string($rss_content);
            if ($rss === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                throw new Exception("Unable to analyze the feed content.");
            }
            
            $items = null;
            if (isset($rss->channel) && isset($rss->channel->item)) {
                $items = $rss->channel->item;
            } elseif (isset($rss->item)) {
                $items = $rss->item;
            } elseif (isset($rss->entry)) {
                $items = $rss->entry;
            }
            
            if (!$items || count($items) == 0) {
                throw new Exception("No items found in the feed.");
            }
            
            foreach ($items as $item) {
                $title = isset($item->title) ? (string)$item->title : '';
                
                if (isset($item->link)) {
                    if ($item->link instanceof SimpleXMLElement && isset($item->link['href'])) {
                        $link = (string)$item->link['href'];
                    } else {
                        $link = (string)$item->link;
                    }
                } else {
                    $link = '';
                }
                
                $description = '';
                if (isset($item->description)) {
                    $description = (string)$item->description;
                } elseif (isset($item->content)) {
                    $description = (string)$item->content;
                } elseif (isset($item->summary)) {
                    $description = (string)$item->summary;
                } elseif (isset($item->children('content', true)->encoded)) {
                    $description = (string)$item->children('content', true)->encoded;
                }
                
                $pub_date = null;
                if (isset($item->pubDate)) {
                    $pub_date = (string)$item->pubDate;
                } elseif (isset($item->published)) {
                    $pub_date = (string)$item->published;
                } elseif (isset($item->updated)) {
                    $pub_date = (string)$item->updated;
                } elseif (isset($item->children('dc', true)->date)) {
                    $pub_date = (string)$item->children('dc', true)->date;
                }
                
                if (empty($pub_date)) {
                    $pub_date = date('Y-m-d H:i:s');
                }
                
                try {
                    $date_obj = new DateTime($pub_date);
                    $mysql_date = $date_obj->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $mysql_date = date('Y-m-d H:i:s');
                }
                
                $image_url = '';
                
                if (isset($item->enclosure) && isset($item->enclosure['url'])) {
                    $enclosure_type = isset($item->enclosure['type']) ? (string)$item->enclosure['type'] : '';
                    if (strpos($enclosure_type, 'image/') === 0 || empty($enclosure_type)) {
                        $image_url = (string)$item->enclosure['url'];
                    }
                }
                
                if (empty($image_url) && isset($item->children('media', true)->content)) {
                    $media = $item->children('media', true);
                    if (isset($media->content) && isset($media->content['url'])) {
                        $image_url = (string)$media->content['url'];
                    } elseif (isset($media->thumbnail) && isset($media->thumbnail['url'])) {
                        $image_url = (string)$media->thumbnail['url'];
                    }
                }
                
                if (empty($image_url) && !empty($description)) {
                    preg_match('/<img.+?src=[\'"](?P<src>.+?)[\'"].*?>/i', $description, $matches);
                    if (isset($matches['src'])) {
                        $image_url = $matches['src'];
                    }
                }
                
                if (!empty($link)) {
                    $check_stmt = $conn->prepare("SELECT id FROM news_cache WHERE link = ? AND feed_id = ?");
                    $check_stmt->bind_param("si", $link, $feed_id);
                    $check_stmt->execute();
                    $exists = $check_stmt->get_result()->num_rows > 0;
                    $check_stmt->close();
                    
                    if (!$exists) {
                        $insert_stmt = $conn->prepare("INSERT INTO news_cache (feed_id, title, link, description, pub_date, image_url) VALUES (?, ?, ?, ?, ?, ?)");
                        $insert_stmt->bind_param("isssss", $feed_id, $title, $link, $description, $mysql_date, $image_url);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                        $new_items++;
                    }
                }
            }
            
            $conn->query("UPDATE feeds SET last_updated = NOW() WHERE id = $feed_id");
            
            $total_processed++;
            $total_new += $new_items;
            
        } catch (Exception $e) {
            $feed_error = $e->getMessage();
            $errors[] = "Erro ao processar feed '$feed_name': " . $feed_error;
        }
        
        $processed_feeds[] = [
            'id' => $feed_id,
            'name' => $feed_name,
            'count' => $new_items,
            'error' => $feed_error
        ];
    }
    
    $success = true;
    $message = "Update completed!<br> <b>$total_new</b> new news items processed from <b>$total_processed</b> feeds.";
    
    if (count($errors) > 0) {
        $error_message = "Some feeds could not be processed.";
    }
    
} catch (Exception $e) {
    $success = false;
    $message = "Error updating feeds: " . $e->getMessage();
}

if ($is_ajax) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'total_processed' => $total_processed,
        'total_new' => $total_new,
        'feeds' => $processed_feeds,
        'errors' => $errors
    ]);
    exit();
}

if (isset($success) && $success) {
    $_SESSION['success_message'] = $message;
    if (count($errors) > 0) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
} else {
    $_SESSION['error_message'] = $message;
}

header("Location: index.php");
exit();
?>