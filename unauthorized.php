<?php
session_start();
include 'includes/header.php';
?>

<div class="content-wrapper">
    <section class="content">
        <div class="error-page">
            <h2 class="headline text-danger">403</h2>
            <div class="error-content">
                <h3><i class="fas fa-exclamation-triangle text-danger"></i> Access Denied</h3>
                <p>
                    You don't have permission to access this page.
                    <?php if (isset($_SESSION['error'])): ?>
                    <br><small><?= htmlspecialchars($_SESSION['error']) ?></small>
                    <?php unset($_SESSION['error']); endif; ?>
                </p>
                <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>