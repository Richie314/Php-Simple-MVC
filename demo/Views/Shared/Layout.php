<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>
        <?= htmlspecialchars(string: $title) ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            padding-top: 70px;
        }
        footer {
            margin-top: 40px;
            padding: 20px 0;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
            text-align: center;
        }
    </style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?= $P ?>">Demo Site</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $P ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $P ?>/contact">Contact</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<h1 class="m-4">
    <?= htmlspecialchars(string: $title) ?>
</h1>

<!-- Main Content -->
<main class="container">
    <?php $RenderBody(); ?>
</main>

<!-- Footer -->
<footer>
    <p>&copy; <?= date(format: 'Y') ?> Demo PHP Site</p>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>