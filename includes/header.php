<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS Reader</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="styles/main.css" rel="stylesheet">
	<link rel="icon" type="image/png" href="images/rss.png"/>

    <style>
        .news-card {
            transition: transform 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card-img-top {
            max-height: 300px;
            object-fit: cover;
        }
        
        .card-text img {
            max-width: 100%;
            height: auto;
        }
        
        .bg-purple {
            background-color: #6f42c1 !important;
        }
        
        .btn-purple {
            background-color: #6f42c1;
            border-color: #6f42c1;
            color: white;
        }
        
        .btn-purple:hover {
            background-color: #5a32a3;
            border-color: #5a32a3;
            color: white;
        }
        
        .btn-outline-purple {
            color: #6f42c1;
            border-color: #6f42c1;
        }
        
        .btn-outline-purple:hover {
            background-color: #6f42c1;
            color: white;
        }
        
        .text-purple {
            color: #6f42c1 !important;
        }

        .pagination .page-link {
            color: #6f42c1;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #6f42c1;
            border-color: #6f42c1;
            color: white;
        }
        
        .dropdown-item:active {
            background-color: #6f42c1;
            color: white;
        }
        a {
            text-decoration: none;
        }
        a:hover,
        a:visited,
        a:active {
            text-decoration: none;
        }
        .card-body a,
        .text-muted a {
            color: inherit;
            text-decoration: none;
        }
        .card-body a:hover,
        .text-muted a:hover {
            text-decoration: none;
        }
        .modal-body .bi {
            color: #444;
            transition: color 0.2s ease;
        }
        .modal-body .bi-linkedin:hover {
            color: #0A66C2 !important;
        }
        .modal-body .bi-instagram:hover {
            color: #E4405F !important;
        }
        .modal-body .bi-github:hover {
            color: #333 !important;
        }
        .modal-body .bi-globe:hover {
            color: #ff6600 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/rss.png" width="32" height="32" alt="RSS Icon">&nbsp; RSS Reader
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li>
                        <a class="nav-link" href="#" id="update-all-news-nav"><i class="fas fa-sync-alt" style="color: #009900;"></i> Refresh News</a>
                    </li>
                    
					<?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="fas fa-cog" style="color: #FF6600;"></i> Manage
						</a>
						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
							<li>
								<a class="dropdown-item" href="feeds.php">
									<i class="fas fa-rss"></i> RSS Sources
								</a>
							</li>
							<li>
								<a class="dropdown-item" href="add_feed.php">
									<i class="fas fa-plus"></i> Add Feed
								</a>
							</li>
							<li><hr class="dropdown-divider"></li>
							<li>
								<a class="dropdown-item" href="change_password.php">
									<i class="fas fa-key"></i> Change Password
								</a>
							</li>
						</ul>
					</li>
					<?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="fas fa-info-circle" style="color: #6666FF;"></i> About</a>
                    </li>
                    
                    <li class="nav-item">
                        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt" style="color: #FF3333;"></i> Logout
                            </a>
                        <?php else: ?>
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt" style="color: #33CC33;"></i> Login
                            </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title" id="updateModalLabel"><i class="fas fa-sync-alt"></i>&nbsp; Feed Update</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="update-status" class="text-center">
                        <div class="spinner-border text-purple" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Starting feed update...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title" id="aboutModalLabel"><i class="fas fa-info-circle"></i>&nbsp; About</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="images/rss.png" width="64" height="64" alt="RSS Icon" class="mb-3">
                    <h4>RSS Reader</h4>
                    <p class="text-muted">Version 1.0</p>
                    <p>A simple and efficient RSS Reader to stay updated with your favorite sources.</p>
                    <p>By: <br><b>Marcos Henrique</b></p>
                    <hr>
                    <div class="d-flex justify-content-center gap-3 fs-3">
                        <a href="https://www.linkedin.com/in/user-marcoshenrique" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://www.instagram.com/100security" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="https://github.com/100security" target="_blank" title="GitHub"><i class="bi bi-github"></i></a>
                        <a href="https://www.100security.com.br" target="_blank" title="Site"><i class="bi bi-globe"></i></a>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <main>