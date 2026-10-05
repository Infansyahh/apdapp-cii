<?php
require __DIR__ . '/config.php';

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT email, password_hash, nama FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['email'] = $user['email'];
        $_SESSION['nama']  = $user['nama'];
        header('Location: dashboard.php?login=1');
        exit;
    }
    $pesan = 'Email atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - APD CII BOGOR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/adminkit/css/app.css">
    <link rel="stylesheet" href="assets/css/vue3-toastify.css">
    <link rel="stylesheet" href="style.css">
    <script>
        (function() {
            var t = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
</head>
<body data-theme="default" data-layout="fluid">
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
    <button type="button" class="btn btn-outline-secondary theme-toggle-btn" title="Beralih Tema" aria-label="Beralih Tema">
        <i data-feather="moon"></i>
    </button>
</div>
<main class="d-flex w-100 h-100">
    <div class="container d-flex flex-column">
        <div class="row vh-100">
            <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto d-table h-100">
                <div class="d-table-cell align-middle">

                    <div class="text-center mt-4">
                        <h1 class="h2">APD CII BOGOR</h1>
                        <p class="lead">Login untuk mengelola stok &amp; monitoring APD</p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="m-sm-3">
                                <?php if ($pesan) echo flash_div($pesan, 'danger'); ?>

                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input class="form-control form-control-lg" type="email" name="email" required
                                               autofocus placeholder="admin@apdcii.local">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input class="form-control form-control-lg" type="password" name="password" required
                                               placeholder="Masukkan password">
                                    </div>
                                    <div class="d-grid gap-2 mt-3">
                                        <button type="submit" class="btn btn-lg btn-primary">Masuk</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-3">
                        Butuh input pengambilan APD? <a href="index.php">Form Pengambilan</a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</main>

<script src="assets/adminkit/js/app.js"></script>
<script src="assets/js/toast.iife.js"></script>
<script src="assets/js/theme-toggle.js"></script>
</body>
</html>
