<?php
session_start();
require_once 'config/db.php';

if (isset($_POST['delete_news']) && isset($_POST['news_id']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $news_id = (int)$_POST['news_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM news_cache WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Error preparing the query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $news_id);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Error executing the deletion: " . $stmt->error);
        }
        
        $stmt->close();
        
        $_SESSION['success_message'] = "News item successfully deleted!";
    } 
    catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting news item: " . $e->getMessage();
    }
    
    $redirect_url = 'index.php';
    $params = [];
    
    if (isset($_GET['page'])) $params[] = 'page=' . (int)$_GET['page'];
    if (isset($_GET['search'])) $params[] = 'search=' . urlencode($_GET['search']);
    if (isset($_GET['category'])) $params[] = 'category=' . urlencode($_GET['category']);
    
    if (!empty($params)) {
        $redirect_url .= '?' . implode('&', $params);
    }
    
    header("Location: $redirect_url");
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_term = '%' . $conn->real_escape_string($search) . '%';
    $search_condition = " AND (n.title LIKE '$search_term' OR n.link LIKE '$search_term' OR n.description LIKE '$search_term' OR n.pub_date LIKE '$search_term')";
}

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$category_condition = '';
if (!empty($category) && $category !== 'All') {
    $category_condition = " AND f.category = '" . $conn->real_escape_string($category) . "'";
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page > 1) ? ($page - 1) * $per_page : 0;

$total_query = "SELECT COUNT(*) as total FROM news_cache n JOIN feeds f ON n.feed_id = f.id WHERE 1=1" . $search_condition . $category_condition;
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_news = $total_row['total'];
$total_pages = ceil($total_news / $per_page);

$query = "SELECT n.*, f.name as feed_name, f.category as feed_category
          FROM news_cache n
          JOIN feeds f ON n.feed_id = f.id
          WHERE 1=1" . $search_condition . $category_condition . "
          ORDER BY n.pub_date DESC
          LIMIT $offset, $per_page";
$result = $conn->query($query);

$categories_query = "SELECT DISTINCT category FROM feeds ORDER BY category";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories[] = $cat['category'] ?: 'All';
    }
}

$debug = false;
if ($debug) {
    echo "<div class='alert alert-info'>Number of news items found: " . $result->num_rows . "</div>";
}

require_once 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['warning_message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['warning_message'] ?>
            <?php unset($_SESSION['warning_message']); ?>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error_message'] ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    </div>
<?php endif; ?>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-center">
            <h1 class="display-4">
                <img src="images/rss.png" width="75" height="75">
                Latest News
            </h1>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12 d-flex flex-column flex-md-row justify-content-center align-items-center">
            <div class="dropdown me-md-2 mb-2 mb-md-0 w-100 w-md-auto"> <button class="btn btn-outline-purple dropdown-toggle w-100" type="button" id="categoryDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-folder"></i> <?= !empty($category) ? htmlspecialchars($category) : 'All Categories' ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end w-100" aria-labelledby="categoryDropdown"> <li>
                        <a class="dropdown-item <?= empty($category) ? 'active' : '' ?>" href="index.php<?= !empty($search) ? '?search=' . urlencode($search) : '' ?>">
                            All
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php foreach ($categories as $cat):
                        if (empty($cat) || $cat === 'All') continue;
                        $is_active = $category === $cat;
                        $url_params = [];
                        if (!empty($search)) $url_params[] = 'search=' . urlencode($search);
                        if (!empty($cat)) $url_params[] = 'category=' . urlencode($cat);
                        $url = 'index.php' . (!empty($url_params) ? '?' . implode('&', $url_params) : '');
                    ?>
                    <li>
                        <a class="dropdown-item <?= $is_active ? 'active' : '' ?>" href="<?= $url ?>">
                            <?= htmlspecialchars($cat) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <form action="index.php" method="GET" class="d-flex w-100"> <?php if (!empty($category)): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <?php endif; ?>
                <div class="input-group w-100"> <input type="text" name="search" class="form-control" placeholder="Search news..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-purple">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="index.php<?= !empty($category) ? '?category=' . urlencode($category) : '' ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
        
    <?php if (!empty($search) || !empty($category)): ?>
        <div class="alert alert-info">
            <?php if (!empty($search) && !empty($category) && $category !== 'All'): ?>
                Search results for: <strong><?= htmlspecialchars($search) ?></strong> 
                in the category <strong><?= htmlspecialchars($category) ?></strong>
            <?php elseif (!empty($search)): ?>
                Search results for: <strong><?= htmlspecialchars($search) ?></strong>
            <?php elseif (!empty($category) && $category !== 'All'): ?>
                News from the category: <strong><?= htmlspecialchars($category) ?></strong>
            <?php endif; ?>
            (<?= $total_news ?> <?= $total_news == 1 ? 'result' : 'results' ?> found)
        </div>
    <?php endif; ?>
    
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card mb-4 news-card position-relative">
                <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 delete-news-btn" 
                            data-id="<?= $row['id'] ?>" 
                            data-title="<?= htmlspecialchars($row['title']) ?>"
                            onclick="event.stopPropagation();">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
						  <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/>
						</svg>
                    </button>
                <?php endif; ?>
                
                <div class="row g-0" onclick="window.open('<?= htmlspecialchars($row['link']) ?>', '_blank')">
                    <?php if (!empty($row['image_url'])): ?>
                        <div class="col-md-2">
                            <img src="<?= htmlspecialchars($row['image_url']) ?>" class="img-fluid rounded-start h-100 w-100 object-fit-cover" alt="<?= htmlspecialchars($row['title']) ?>" onerror="this.style.display='none'">
                        </div>
                        <div class="col-md-10">
                    <?php else: ?>
                        <div class="col-12">
                    <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title text-purple">
                                <?php
                                $url_parts = parse_url($row['link']);
                                $domain = isset($url_parts['host']) ? $url_parts['host'] : '';
                                if (!empty($domain)):
                                ?>
                                    <img src="https://www.google.com/s2/favicons?domain=<?= urlencode($domain) ?>&sz=32" 
                                            alt="Favicon" class="me-2" style="width: 16px; height: 16px; vertical-align: middle;">
                                <?php else: ?>
                                    <i class="fas fa-rss-square me-2"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($row['title']) ?>
                            </h5>
                            <p class="text-muted">
                                <small>
                                    <img src="images/calendar.png" width="16" height="16">
                                    <?= date('d/m/Y H:i', strtotime($row['pub_date'])) ?> &nbsp;
                                    <img src="images/rss.png" width="16" height="16">
                                    <b><?= htmlspecialchars($row['feed_name']) ?></b>
                                    
                                    <?php if (!empty($row['feed_category']) && $row['feed_category'] !== 'All'): ?>
                                        &nbsp;
                                        <span class="text-primary">
                                            <img src="images/folder.png" width="16" height="16">
                                            <?= htmlspecialchars($row['feed_category']) ?>
                                        </span>
                                    <?php endif; ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    $url_params = [];
                    if (!empty($search)) $url_params[] = 'search=' . urlencode($search);
                    if (!empty($category)) $url_params[] = 'category=' . urlencode($category);
                    $url_params_str = !empty($url_params) ? '&' . implode('&', $url_params) : '';
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?= $url_params_str ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= $url_params_str ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $total_pages ?><?= $url_params_str ?>"><?= $total_pages ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= $url_params_str ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <?php if (!empty($search) || !empty($category)): ?>
                No results found 
                <?php if (!empty($search)): ?>
                    for the search: <strong><?= htmlspecialchars($search) ?></strong>
                <?php endif; ?>
                <?php if (!empty($category) && $category !== 'All'): ?>
                    <?= !empty($search) ? ' in' : 'in' ?> category: <strong><?= htmlspecialchars($category) ?></strong>
                <?php endif; ?>
                <br><br>
                <a href="index.php" class="alert-link"><i class="fas fa-trash-alt"></i> Clear filters</a>
            <?php else: ?>
                No news found. Please, 
                <?php if ($total_news == 0): ?>
                    <a href="add_feed.php" class="alert-link">add RSS sources first</a>.
                <?php else: ?>
                    update the existing RSS sources.
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="deleteNewsModal" tabindex="-1" aria-labelledby="deleteNewsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteNewsModalLabel">Delete?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>Are you sure you want to <b>delete</b> this news item?</p>
                📰 <p class="fw-bold" id="newsTitle"></p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-purple" data-bs-dismiss="modal"><i class="fas fa-times"></i>&nbsp; Cancel</button>
                <form id="deleteNewsForm" method="POST" action="index.php">
                    <input type="hidden" name="news_id" id="newsIdToDelete">
                    <input type="hidden" name="delete_news" value="1">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i>&nbsp; Yes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.delete-news-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const newsId = this.getAttribute('data-id');
                const newsTitle = this.getAttribute('data-title');
                
                document.getElementById('newsIdToDelete').value = newsId;
                document.getElementById('newsTitle').textContent = newsTitle;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteNewsModal'));
                deleteModal.show();
            });
        });
    });
</script>

<style>
    .news-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .news-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
    }
    
    .delete-news-btn {
        opacity: 0.7;
        z-index: 10;
        transition: opacity 0.2s ease;
    }
    
    .news-card:hover .delete-news-btn {
        opacity: 1;
    }
</style>

<?php require_once 'includes/footer.php'; ?>