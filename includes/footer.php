</main>    
    <footer class="bg-dark text-white py-3 mt-5">
        <div class="container">
            <div class="row align-items-center">
				<div class="col-md-12 align-items-center">
                    <center><img src="images/100security.png" width="32" height="32" alt="100SECURITY"> <b>100SECURITY</b>&nbsp; : &nbsp;<a href="https://github.com/100security/rss-reader" target="_blank" class="text-white">github.com/100security/rss-reader</a></center>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const updateAllButton = document.getElementById('update-all-news-nav');
            if (updateAllButton) {
                updateAllButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
                    updateModal.show();
                    
                    const updateStatus = document.getElementById('update-status');
                    updateStatus.innerHTML = `
                        <div class="spinner-border text-purple" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Starting feed update...</p>
                    `;
                    
                    fetch('update_all_feeds.php?ajax=1')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                let feedResults = '';
                                
                                if (data.feeds && data.feeds.length > 0) {
                                    feedResults = '<ul class="list-group mt-3">';
                                    data.feeds.forEach(feed => {
                                        const statusClass = feed.error ? 'text-danger' : 'text-success';
                                        const statusIcon = feed.error ? 'fa-times-circle' : 'fa-check-circle';
                                        const statusText = feed.error ? feed.error : `<b>${feed.count}</b> new news items`;
                                        
                                        feedResults += `
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>${feed.name}</span>
                                                <span class="${statusClass}">
                                                    <i class="fas ${statusIcon}"></i> ${statusText}
                                                </span>
                                            </li>
                                        `;
                                    });
                                    feedResults += '</ul>';
                                }
                                
                                updateStatus.innerHTML = `
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> ${data.message}
                                    </div>
                                    ${feedResults}
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-purple" onclick="window.location.reload()">
                                            <i class="fas fa-sync-alt"></i> Update
                                        </button>
                                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                                            <i class="fas fa-times"></i> Close
                                        </button>
                                    </div>
                                `;
                            } else {
                                updateStatus.innerHTML = `
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle"></i> ${data.message || 'An error occured during the update.'}
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                                            <i class="fas fa-times"></i> Close
                                        </button>
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Request error:', error);
                            updateStatus.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i> An error occurred while processing the request: ${error.message}.
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                </div>
                            `;
                        });
                });
            }
        });
    </script>
</body>
</html>