$(document).ready(function() {
    $("#update-all-news").click(function() {
        updateAllFeeds();
    });
    
    $("#update-all-news-nav").click(function(e) {
        e.preventDefault();
        $('#updateModal').modal('show');
        updateAllFeeds();
    });
    
    function updateAllFeeds() {
        const $updateStatus = $("#update-status");
        
        $.ajax({
            url: "get_feeds.php",
            type: "GET",
            dataType: "json",
            success: function(feeds) {
                if (feeds.length === 0) {
                    $updateStatus.html('<div class="alert alert-warning">No feeds found to update.</div>');
                    return;
                }
                
                let totalFeeds = feeds.length;
                let processedFeeds = 0;
                let successFeeds = 0;
                
                function processNextFeed(index) {
                    if (index >= totalFeeds) {
                        $updateStatus.html(`
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <strong>Update completed!</strong><br>
                                ${successFeeds} out of ${totalFeeds} feeds successfully updated.
                            </div>
                        `);
                        
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                        
                        return;
                    }
                    
                    const feed = feeds[index];
                    const progress = Math.round((index / totalFeeds) * 100);
                    
                    $updateStatus.html(`
                        <div class="progress mb-3">
                            <div class="progress-bar bg-purple" role="progressbar" style="width: ${progress}%" 
                                 aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100">
                                ${progress}%
                            </div>
                        </div>
                        <p>
                            <i class="fas fa-sync-alt fa-spin"></i> Updating feed ${index + 1} of ${totalFeeds}:<br>
                            <strong>${feed.name}</strong>
                        </p>
                    `);
                    
                    $.ajax({
                        url: `process_feed_ajax.php?id=${feed.id}`,
                        type: "GET",
                        dataType: "json",
                        success: function(response) {
                            processedFeeds++;
                            
                            if (response.success) {
                                successFeeds++;
                            }
                            
                            processNextFeed(index + 1);
                        },
                        error: function() {
                            processedFeeds++;
                            
                            processNextFeed(index + 1);
                        }
                    });
                }
                
                processNextFeed(0);
            },
            error: function() {
                $updateStatus.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error retrieving the feed list.</div>');
            }
        });
    }
    
    $('img').on('error', function() {
        $(this).hide();
    });
});