<?php
session_start(); // Start session if needed

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
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
        if (file_exists($page)) {
            include $page;
        } else {
            echo "<div class='alert alert-error'>Page not found!</div>";
        }
        ?>
    </div>

    <!-- JavaScript for Dynamic Loading -->
    <script>
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = e.target.getAttribute('data-page');

                // Fetch and load content
                fetch(page)
                    .then(res => res.text())
                    .then(html => {
                        document.getElementById('content-container').innerHTML = html;
                        history.pushState(null, '', `?page=${page}`);
                    })
                    .catch(err => console.error('Error loading page:', err));
            });
        });

        // Handle browser back/forward
        window.addEventListener('popstate', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'index.php';

            fetch(page)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('content-container').innerHTML = html;
                });
        });
    </script>
</body>

</html>