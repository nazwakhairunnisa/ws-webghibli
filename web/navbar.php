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
    <div class="logo">Ghibli<br>Wiki</div>

    <nav class="nav-links">
      <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? '../index.php' : 'index.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'class="active"' : ''; ?>>Home</a>
      <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? '../director.php' : 'director.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'director.php') ? 'class="active"' : ''; ?>>Director</a>

      <div class="dropdown">
        <button class="dropbtn">Content ▾</button>
        <div class="dropdown-content">
          <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? 'films.php' : 'content/films.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'films.php') ? 'class="active"' : ''; ?>>Film</a>
          <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? 'shorts.php' : 'content/shorts.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'shorts.php') ? 'class="active"' : ''; ?>>Shorts</a>
          <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) == 'content') ? 'series.php' : 'content/series.php'; ?>" <?php echo (basename($_SERVER['PHP_SELF']) == 'series.php') ? 'class="active"' : ''; ?>>Series</a>
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

<script>
  // Wait for DOM to load
  document.addEventListener('DOMContentLoaded', function() {
    const dropbtn = document.querySelector('.dropbtn');
    const dropdownContent = document.querySelector('.dropdown-content');
    const dropdown = document.querySelector('.dropdown');

    if (dropbtn && dropdownContent) {
      dropbtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownContent.classList.toggle('show');
      });

      // Close when clicking outside
      document.addEventListener('click', function(event) {
        if (!dropdown.contains(event.target)) {
          dropdownContent.classList.remove('show');
        }
      });

      // Close when clicking a link
      dropdownContent.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
          dropdownContent.classList.remove('show');
        });
      });
    }
  });
</script>