<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'config/db.php';

if (isset($_POST['delete_feed']) && isset($_POST['feed_id'])) {
    $id = (int)$_POST['feed_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM feeds WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Error preparing the query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Error executing the deletion: " . $stmt->error);
        }
        
        $stmt->close();
        
        $conn->query("DELETE FROM news_cache WHERE feed_id = $id");
        
        $_SESSION['success_message'] = "RSS source successfully deleted!";
    } 
    catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting: " . $e->getMessage();
    }
    
    header("Location: feeds.php");
    exit;
}

require_once 'includes/header.php';

$result = $conn->query("SELECT f.*, (SELECT COUNT(*) FROM news_cache WHERE feed_id = f.id) AS news_count FROM feeds f ORDER BY name");
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="display-4"><i class="fas fa-rss"></i>&nbsp; RSS Sources</h1>
                <div>
                    <a href="add_feed.php" class="btn btn-success me-2">
                        <i class="fas fa-plus"></i> Add
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success_message'] ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error_message'] ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success">RSS sources successfully added!</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">
                    Feed successfully updated! <b><?= isset($_GET['count']) ? $_GET['count'] . '</b> news processed.' : '' ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">An error occured while processing the feed. Check the log file for more details.</div>
            <?php endif; ?>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="col">
                            <div class="card h-100">
								<div class="card-header bg-purple text-white d-flex justify-content-between align-items-center">
									<h5 class="mb-0"><?= htmlspecialchars($row['name']) ?></h5>
									<div>
										<span class="badge bg-light text-dark"><?= htmlspecialchars($row['category'] ?? 'Todas') ?></span>
										<span class="badge bg-light text-dark"><?= $row['news_count'] ?></span>
									</div>
								</div>
                                <div class="card-body">
                                    <p class="card-text text-truncate">
                                        <a href="<?= htmlspecialchars($row['url']) ?>"  class="text-decoration-none" title="<?= htmlspecialchars($row['url']) ?>">
                                            <?= htmlspecialchars($row['url']) ?>
                                        </a>
                                    </p>
                                    <p class="text-muted small">
                                        <i class="fas fa-sync-alt"></i> Updated: <?= date('d/m/Y h:i:s A', strtotime($row['last_updated'])) ?>
                                    </p>
                                    <p class="text-muted small">
                                        <i class="far fa-folder"></i> Category: <b><?= htmlspecialchars($row['category']) ?></b>
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent d-flex justify-content-between">
                                    <a href="process_feed.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-purple">
                                        <i class="fas fa-sync-alt"></i> Update
                                    </a>
                                    <div>
                                        <a href="add_feed.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No RSS sources registred yet.
					<br><br>
                    <a href="add_feed.php" class="alert-link"><i class="fas fa-plus"></i>&nbsp; Add one now</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Delete?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>Are you sure you want to <b>delete</b> this RSS source?</p>
                📰 <p class="fw-bold" id="feedNameToDelete"></p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <hr>
            <div class="modal-footer justify-content-center align-items-center">
                <button type="button" class="btn btn-purple" data-bs-dismiss="modal"><i class="fas fa-times"></i>&nbsp; Cancel</button>
                <form id="deleteForm" method="POST" action="feeds.php">
                    <input type="hidden" name="feed_id" id="feedIdToDelete">
                    <input type="hidden" name="delete_feed" value="1">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i>&nbsp; Yes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, name) {
        document.getElementById('feedNameToDelete').textContent = name;
        
        document.getElementById('feedIdToDelete').value = id;
        
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>