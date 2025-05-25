<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$name = $url = $category = '';
$id = null;
$is_edit = false;

$check_column = $conn->query("SHOW COLUMNS FROM feeds LIKE 'category'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE feeds ADD COLUMN category VARCHAR(50) DEFAULT 'All'");
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM feeds WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row['name'];
        $url = $row['url'];
        $category = isset($row['category']) ? $row['category'] : 'All';
        $is_edit = true;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);
    $category = trim($_POST['category']);
    
    if (empty($category)) {
        $category = 'All';
    }
    
    $error = null;
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL.";
    } else {
        $is_valid_feed = false;
        
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
            
            $rss_content = @file_get_contents($url, false, $context);
            
            if ($rss_content === false) {
                throw new Exception("Unable to access the URL. Please check if the address is correct and accessible.");
            }
            
            if (strpos($rss_content, '<?xml') !== false || strpos($rss_content, '<rss') !== false || strpos($rss_content, '<feed') !== false) {
                libxml_use_internal_errors(true);
                
                $rss = simplexml_load_string($rss_content);
                
                if ($rss !== false) {
                    if (
                        (isset($rss->channel) && (isset($rss->channel->item) || isset($rss->channel->title))) ||
                        isset($rss->item) ||
                        isset($rss->entry) ||
                        $rss->getName() == 'rss' || $rss->getName() == 'feed'
                    ) {
                        $is_valid_feed = true;
                    }
                } else {
                    $errors = libxml_get_errors();
                    libxml_clear_errors();
                    
                    $error_msgs = [];
                    foreach ($errors as $err) {
                        $error_msgs[] = "Line {$err->line}: {$err->message}";
                    }
                    
                    if (!empty($error_msgs)) {
                        throw new Exception("Errors while parsing the XML: " . implode("; ", $error_msgs));
                    } else {
                        throw new Exception("The content could not be parsed as valid XML.");
                    }
                }
            } else {
                $headers = get_headers($url, 1);
                if (isset($headers['Content-Type'])) {
                    $content_type = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
                    if (strpos($content_type, 'application/rss+xml') !== false || 
                        strpos($content_type, 'application/atom+xml') !== false ||
                        strpos($content_type, 'application/xml') !== false ||
                        strpos($content_type, 'text/xml') !== false) {
                        $is_valid_feed = true;
                    } else {
                        throw new Exception("The content does not appear to be an RSS/Atom feed (Content-Type: $content_type).");
                    }
                } else {
                    throw new Exception("Unable to determine the content type of the URL.");
                }
            }
        } catch (Exception $e) {
            $error = "Error checking the feed: " . $e->getMessage();
        }
        
        if ($is_valid_feed) {
            if ($is_edit) {
                $stmt = $conn->prepare("UPDATE feeds SET name = ?, url = ?, category = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $url, $category, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO feeds (name, url, category) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $url, $category);
            }
            
            if ($stmt->execute()) {
                $new_id = $is_edit ? $id : $stmt->insert_id;
                $stmt->close();
                
                $_SESSION['success_message'] = $is_edit ? "Feed successfully updated!" : "Feed successfully added!";
                
                header("Location: process_feed.php?id=$new_id&added=1");
                exit();
            } else {
                $error = "Error saving to the database: " . $conn->error;
            }
        } else if (!isset($error)) {
            $error = "The provided URL does not appear to be a valid RSS feed. Please check and try again.";
        }
    }
    
    if ($error) {
        $_SESSION['error_message'] = $error;
    }
}

$categories = [];
$categories_result = $conn->query("SELECT DISTINCT category FROM feeds ORDER BY category");
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-purple text-white">
                    <h3 class="mb-0"><i class="fas fa-plus"></i> <?= $is_edit ? 'Edit RSS Source' : 'Add RSS Source' ?></h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error_message'] ?>
                            <?php unset($_SESSION['error_message']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label for="name">RSS Source</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="url">RSS Feed URL</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-link"></i></span>
                                <input type="url" class="form-control" id="url" name="url" value="<?= htmlspecialchars($url) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="category">Category</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-folder"></i></span>
                                <input type="text" class="form-control" id="category" name="category" 
                                       value="<?= htmlspecialchars($category) ?>" 
                                       list="category-list" placeholder="E.g.: Cloud, CyberSecurity, AI...">
                                <datalist id="category-list">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <small class="form-text text-muted">
                                Choose an existing category or create a new one. If left blank, it will be cassified as "All".
                            </small>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i>
                                <?= $is_edit ? 'Update' : 'Save' ?>
                            </button>
                            <a href="feeds.php" class="btn btn-danger text-white"><i class="fas fa-times"></i> Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>