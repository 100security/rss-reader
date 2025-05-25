<?php
session_start();

require_once 'includes/auth_check.php';

require_once 'config/db.php';

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "ID do feed não fornecido.";
    header("Location: feeds.php");
    exit();
}

$feed_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM feeds WHERE id = ?");
$stmt->bind_param("i", $feed_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Feed not found.";
    header("Location: feeds.php");
    exit();
}

$feed = $result->fetch_assoc();
$stmt->close();

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
    
    $rss_content = @file_get_contents($feed['url'], false, $context);
    
    if ($rss_content === false) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $feed['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $rss_content = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($rss_content === false || empty($rss_content)) {
                throw new Exception("Unable to retrieve feed content via cURL: " . $curl_error);
            }
        } else {
            throw new Exception("Unable to retrieve the feed content. Please check if the URL is accessible and if PHP has permission to access external URLs.");
        }
    }
    
    libxml_use_internal_errors(true);
    
    $rss = simplexml_load_string($rss_content);
    
    if ($rss === false) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        $error_msgs = [];
        foreach ($errors as $err) {
            $error_msgs[] = "Linha {$err->line}: {$err->message}";
        }
        
        if (!empty($error_msgs)) {
            throw new Exception("Errors parsing the XML: " . implode("; ", $error_msgs));
        } else {
            throw new Exception("The content could not be parsed as valid XML.");
        }
    }
    
    $count = 0;
    
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
            $stmt = $conn->prepare("SELECT id FROM news_cache WHERE link = ? AND feed_id = ?");
            $stmt->bind_param("si", $link, $feed_id);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            
            if (!$exists) {
                $stmt = $conn->prepare("INSERT INTO news_cache (feed_id, title, link, description, pub_date, image_url) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $feed_id, $title, $link, $description, $mysql_date, $image_url);
                $stmt->execute();
                $stmt->close();
                $count++;
            }
        }
    }
    
    $check_column = $conn->query("SHOW COLUMNS FROM feeds LIKE 'last_updated'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE feeds ADD COLUMN last_updated TIMESTAMP NULL DEFAULT NULL");
    }
    
    $conn->query("UPDATE feeds SET last_updated = NOW() WHERE id = $feed_id");
    
    if (isset($_GET['added'])) {
        $_SESSION['success_message'] = "Feed successfully added and processed! <b>$count</b> news items imported.";
    } else {
    }
} 
catch (Exception $e) {
    error_log("Error processing feed #$feed_id ({$feed['url']}): " . $e->getMessage());
    
    $_SESSION['error_message'] = "Error processing feed: " . $e->getMessage();
    
    header("Location: feeds.php?error=1");
    exit();
}

if (isset($_GET['added'])) {
    header("Location: feeds.php?added=1");
} else {
    header("Location: feeds.php?updated=1&count=$count");
}
exit();
?>