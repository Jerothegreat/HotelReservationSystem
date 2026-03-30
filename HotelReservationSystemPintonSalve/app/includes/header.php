<?php
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? "Jero &amp; Vonn's Six Star Hotel";
$htmlClass = $htmlClass ?? '';
$bodyClass = $bodyClass ?? '';
$headExtras = $headExtras ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo htmlspecialchars($htmlClass, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php echo $headExtras; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap">
</head>
<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
<header class="royal-topbar">
    <div class="royal-topbar-inner royal-container">
        <a class="royal-brand" href="home.php">Jero &amp; Vonn's Royal Chambers</a>
        <nav class="royal-nav" aria-label="Main Navigation">
            <a href="home.php" class="<?= $activePage === 'home' ? 'nav-active' : '' ?>">Home</a>
            <a href="profile.php" class="<?= $activePage === 'profile' ? 'nav-active' : '' ?>">Company's Profile</a>
            <a href="reservation.php" class="<?= $activePage === 'reservation' ? 'nav-active' : '' ?>">Reservation</a>
            <a href="contacts.php" class="<?= $activePage === 'contacts' ? 'nav-active' : '' ?>">Contacts</a>
            <a href="admin.php" class="<?= $activePage === 'admin' ? 'nav-active' : '' ?>">Admin</a>
        </nav>
        <button class="royal-mobile-menu" type="button" aria-label="Open navigation">&#9776;</button>
    </div>
</header>
