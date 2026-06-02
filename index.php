<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/functions.php';

$isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
$appEntryUrl = $isLoggedIn ? 'dashboard.php' : 'login.php';
$loginUrl = 'login.php';

$logoCandidates = [
    'logo/EVSU_Official_Logo.png',
    'logo/EVSU_Official_Logo.jpg',
    'images/logo.svg',
];
$logoSrc = 'images/logo.svg';
foreach ($logoCandidates as $candidate) {
    if (is_file(__DIR__ . '/' . $candidate)) {
        $logoSrc = $candidate;
        break;
    }
}

$bgImage = 'assets/images/evsu-campus-bg.png';
if (!is_file(__DIR__ . '/' . $bgImage)) {
    $bgImage = '';
}

$smallTiles = [
    ['label' => 'Borrow Books', 'icon' => 'fa-hand-holding', 'href' => $appEntryUrl, 'bg' => '#8bc34a'],
    ['label' => 'Return Books', 'icon' => 'fa-undo', 'href' => $isLoggedIn ? 'return.php' : $loginUrl, 'bg' => '#ffc107'],
    ['label' => 'Book Catalog', 'icon' => 'fa-book', 'href' => $isLoggedIn ? 'books.php' : $loginUrl, 'bg' => '#ffffff', 'dark' => true],
    ['label' => 'Members', 'icon' => 'fa-users', 'href' => $isLoggedIn ? 'members.php' : $loginUrl, 'bg' => '#03a9f4'],
    ['label' => 'Transactions', 'icon' => 'fa-exchange-alt', 'href' => $isLoggedIn ? 'transactions.php' : $loginUrl, 'bg' => '#b71c1c'],
    ['label' => 'Overdue Books', 'icon' => 'fa-exclamation-circle', 'href' => $isLoggedIn ? 'overdue.php' : $loginUrl, 'bg' => '#009688'],
    ['label' => 'Penalties', 'icon' => 'fa-file-invoice-dollar', 'href' => $isLoggedIn ? 'penalties_record.php' : $loginUrl, 'bg' => '#ff9800'],
    ['label' => 'Book Metrics', 'icon' => 'fa-chart-line', 'href' => $isLoggedIn ? 'book_metrics.php' : $loginUrl, 'bg' => '#9c27b0'],
    ['label' => 'Notifications', 'icon' => 'fa-bell', 'href' => $isLoggedIn ? 'notifications.php' : $loginUrl, 'bg' => '#cddc39', 'dark' => true],
    ['label' => 'Staff Login', 'icon' => 'fa-sign-in-alt', 'href' => $loginUrl, 'bg' => '#e91e63'],
    ['label' => 'Dashboard', 'icon' => 'fa-chart-bar', 'href' => $isLoggedIn ? 'dashboard.php' : $loginUrl, 'bg' => '#607d8b'],
    ['label' => 'Annual Reports', 'icon' => 'fa-file-pdf', 'href' => $isLoggedIn ? 'reports.php' : $loginUrl, 'bg' => '#795548'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVSU Book Borrowing System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Source Sans 3', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #1a1a1a;
        }

        .portal-header {
            background: #fff;
            border-bottom: 1px solid #e5e5e5;
            padding: 0.75rem 1.5rem;
            position: relative;
            z-index: 20;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .portal-header-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        .portal-header img {
            height: 64px;
            width: auto;
        }
        .portal-header-text {
            text-align: left;
        }
        .portal-header-text .line1 {
            font-family: 'Libre Baskerville', Georgia, serif;
            font-size: clamp(1.1rem, 3vw, 1.65rem);
            font-weight: 700;
            color: #8b0000;
            letter-spacing: 0.02em;
            line-height: 1.15;
        }
        .portal-header-text .line2 {
            font-family: 'Libre Baskerville', Georgia, serif;
            font-size: clamp(0.85rem, 2.2vw, 1.15rem);
            font-weight: 700;
            color: #8b0000;
            letter-spacing: 0.08em;
        }

        .portal-hero {
            flex: 1;
            position: relative;
            padding: 2rem 1rem 1.5rem;
            overflow: hidden;
        }
        .portal-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            <?php if ($bgImage): ?>
            background: url('<?php echo htmlspecialchars($bgImage); ?>') center / cover no-repeat;
            <?php else: ?>
            background: linear-gradient(135deg, #6b7d8f 0%, #9aabbc 50%, #c4d0dc 100%);
            <?php endif; ?>
            filter: blur(4px);
            transform: scale(1.05);
        }
        .portal-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.35);
        }
        .portal-content {
            position: relative;
            z-index: 2;
            max-width: 1100px;
            margin: 0 auto;
        }

        .metro-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: auto;
            gap: 6px;
        }

        .tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-decoration: none;
            color: #fff;
            padding: 1rem 0.75rem;
            min-height: 110px;
            transition: filter 0.15s ease, transform 0.15s ease;
            position: relative;
            overflow: hidden;
        }
        .tile:hover {
            filter: brightness(1.08);
            transform: scale(1.01);
            z-index: 1;
        }
        .tile-lg {
            min-height: 130px;
            grid-column: span 1;
        }
        .tile-lg .tile-icon {
            font-size: 2.75rem;
            margin-bottom: 0.5rem;
            opacity: 0.95;
        }
        .tile-lg .tile-title {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.25;
            text-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        .tile-brand {
            background: #7a0019;
            cursor: default;
            pointer-events: none;
        }
        .tile-brand img {
            height: 72px;
            width: auto;
            margin-bottom: 0.35rem;
        }
        .tile-brand .tile-title {
            font-family: 'Libre Baskerville', Georgia, serif;
            font-size: 0.7rem;
            letter-spacing: 0.04em;
        }
        .tile-green { background: #2e7d32; }
        .tile-coral { background: #c75b4a; }
        .tile-purple { background: #6a4c93; }

        .metro-lower {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 6px;
            margin-top: 6px;
        }
        .tiles-small {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }
        .tile-sm {
            min-height: 88px;
            padding: 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 1.2;
        }
        .tile-sm .tile-icon {
            font-size: 1.35rem;
            margin-bottom: 0.35rem;
        }
        .tile-sm.text-dark { color: #222; }

        .tile-map {
            background: #fff;
            min-height: 100%;
            padding: 0;
            display: block;
        }
        .tile-map iframe {
            width: 100%;
            height: 100%;
            min-height: 280px;
            border: 0;
            display: block;
        }

        .portal-title {
            text-align: center;
            margin-top: 1.75rem;
            font-size: clamp(1.35rem, 4vw, 2rem);
            font-weight: 700;
            color: #111;
            text-shadow: 0 1px 0 rgba(255,255,255,0.8);
        }

        .portal-footer {
            background: #f0f0f0;
            border-top: 1px solid #ddd;
            padding: 1rem 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: #444;
            line-height: 1.6;
            position: relative;
            z-index: 20;
        }

        .logged-in-banner {
            text-align: center;
            margin-bottom: 0.75rem;
        }
        .logged-in-banner a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.92);
            color: #7a0019;
            font-weight: 600;
            padding: 0.45rem 1rem;
            border-radius: 999px;
            text-decoration: none;
            font-size: 0.875rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .logged-in-banner a:hover { background: #fff; }

        @media (max-width: 900px) {
            .metro-grid { grid-template-columns: repeat(2, 1fr); }
            .metro-lower { grid-template-columns: 1fr; }
            .tiles-small { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 520px) {
            .metro-grid { grid-template-columns: 1fr; }
            .tiles-small { grid-template-columns: repeat(2, 1fr); }
            .portal-header-inner { flex-direction: column; text-align: center; }
            .portal-header-text { text-align: center; }
        }
    </style>
</head>
<body>
    <header class="portal-header">
        <div class="portal-header-inner">
            <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="EVSU Logo" onerror="this.style.display='none'">
            <div class="portal-header-text">
                <div class="line1">EASTERN VISAYAS</div>
                <div class="line2">STATE UNIVERSITY</div>
            </div>
        </div>
    </header>

    <main class="portal-hero">
        <div class="portal-content">
            <?php if ($isLoggedIn): ?>
            <div class="logged-in-banner">
                <a href="dashboard.php"><i class="fas fa-arrow-right"></i> Continue to Dashboard (signed in)</a>
            </div>
            <?php endif; ?>

            <div class="metro-grid">
                <a href="<?php echo htmlspecialchars($appEntryUrl); ?>" class="tile tile-lg tile-green">
                    <i class="fas fa-book-reader tile-icon"></i>
                    <span class="tile-title">Book Borrowing<br>System</span>
                </a>

                <div class="tile tile-lg tile-brand">
                    <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="EVSU">
                    <span class="tile-title">EASTERN VISAYAS<br>STATE UNIVERSITY</span>
                </div>

                <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="tile tile-lg tile-coral">
                    <i class="fas fa-user-lock tile-icon"></i>
                    <span class="tile-title">Staff &amp; Admin<br>Login</span>
                </a>

                <a href="<?php echo htmlspecialchars($isLoggedIn ? 'books.php' : $loginUrl); ?>" class="tile tile-lg tile-purple">
                    <i class="fas fa-book-open tile-icon"></i>
                    <span class="tile-title">Library<br>Catalog</span>
                </a>
            </div>

            <div class="metro-lower">
                <div class="tiles-small">
                    <?php foreach ($smallTiles as $tile): ?>
                        <a href="<?php echo htmlspecialchars($tile['href']); ?>"
                           class="tile tile-sm<?php echo !empty($tile['dark']) ? ' text-dark' : ''; ?>"
                           style="background: <?php echo htmlspecialchars($tile['bg']); ?>;">
                            <i class="fas <?php echo htmlspecialchars($tile['icon']); ?> tile-icon"></i>
                            <span><?php echo htmlspecialchars($tile['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="tile-map">
                    <iframe
                        title="Eastern Visayas State University - Tacloban"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://maps.google.com/maps?q=Eastern+Visayas+State+University,+Tacloban+City&amp;t=&amp;z=16&amp;ie=UTF8&amp;iwloc=&amp;output=embed">
                    </iframe>
                </div>
            </div>

            <h1 class="portal-title">EVSU Book Borrowing System</h1>
        </div>
    </main>

    <footer class="portal-footer">
        <p>Eastern Visayas State University — Copyright &copy; 2012-<?php echo date('Y'); ?> All Rights Reserved</p>
        <p>EVSU Book Borrowing System — Library operations portal for staff and administrators.</p>
    </footer>
</body>
</html>
