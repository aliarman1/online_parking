<?php
/**
 * Admin Dashboard for Online Parking System
 */

// Include database connection
require 'database/db.php';

// Ensure user is admin
require_admin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkEase</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.x.x/dist/full.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
    <!-- Include Navbar -->
    <?php include 'admin_navbar.php'; ?>

    <!-- Dynamic Content Container -->
    <div id="content-container" class="p-4">
        <?php
        // Load default content (home page) if no page is specified
        $page = isset($_GET['page']) ? $_GET['page'] : 'a_dashboard.php';

        // Security: Validate page parameter to prevent directory traversal
        $allowed_pages = [
            'a_dashboard.php',
            'a_user.php',
            'add_user.php',
            'edit_user.php'
        ];

        if (in_array($page, $allowed_pages) && file_exists($page)) {
            include $page;
        } else {
            echo "<div class='alert alert-error'>Page not found or access denied!</div>";
        }
        ?>
    </div>

    <!-- JavaScript for Dynamic Loading -->
    <script>
        // List of allowed pages (must match the PHP allowed_pages array)
        const allowedPages = [
            'a_dashboard.php',
            'a_user.php',
            'add_user.php',
            'edit_user.php'
        ];

        // Function to validate page
        function isValidPage(page) {
            return allowedPages.includes(page);
        }

        // Handle navigation link clicks
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = e.target.getAttribute('data-page');

                // Security: Validate page before loading
                if (isValidPage(page)) {
                    // Fetch and load content
                    fetch(page)
                        .then(res => res.text())
                        .then(html => {
                            document.getElementById('content-container').innerHTML = html;
                            history.pushState(null, '', `?page=${page}`);
                        })
                        .catch(err => console.error('Error loading page:', err));
                } else {
                    console.error('Invalid page requested');
                    document.getElementById('content-container').innerHTML =
                        "<div class='alert alert-error'>Page not found or access denied!</div>";
                }
            });
        });

        // Handle browser back/forward
        window.addEventListener('popstate', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'a_dashboard.php';

            // Security: Validate page before loading
            if (isValidPage(page)) {
                fetch(page)
                    .then(res => res.text())
                    .then(html => {
                        document.getElementById('content-container').innerHTML = html;
                    });
            } else {
                document.getElementById('content-container').innerHTML =
                    "<div class='alert alert-error'>Page not found or access denied!</div>";
            }
        });
    </script>
</body>

</html>