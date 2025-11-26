<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? '../style.css' : 'style.css'; ?>" />
  <title><?php echo isset($pageTitle) ? $pageTitle : 'Ghibli Library'; ?></title>
</head>
<body>

  <!-- NAVBAR -->
  <header class="navbar">
    <div class="logo">Ghibli<br>Studio</div>

    <nav class="nav-links">
      <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? '../index.php' : 'index.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'class="active"' : ''; ?>>Home</a>
      <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? '../director.php' : 'director.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'director.php') ? 'class="active"' : ''; ?>>Director</a>
      <!-- <a href="director.php">Director</a> -->

      <div class="dropdown">
        <button class="dropbtn">Content ▾</button>
        <div class="dropdown-content">
          <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? 'films.php' : 'content/films.php'; ?>">Film</a>
          <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? 'shorts.php' : 'content/shorts.php'; ?>">Shorts</a>
          <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? 'series.php' : 'content/series.php'; ?>">Series</a>
        </div>
      </div>

      <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? '../characters.php' : 'characters.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'characters.php') ? 'class="active"' : ''; ?>>Characters</a>
    </nav>

    <div class="menu-toggle">
      <span></span>
      <span></span>
      <span></span>
    </div>
  </header>