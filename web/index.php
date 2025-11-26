<?php
// Function to execute SPARQL queries
function executeQuery($sparqlQuery) {
    $fusekiEndpoint = "http://localhost:3030/ghibli-dataset/sparql";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fusekiEndpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "query=" . urlencode($sparqlQuery));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json"
    ));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Query for Directors
$directorsQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>
PREFIX director: <http://ghibliwiki.org/director/>

SELECT DISTINCT ?name ?born ?nationality ?imageURL
WHERE {
    ?director a ghibli:Director ;
              ghibli:name ?name ;
              ghibli:imageURL ?imageURL.
    
    OPTIONAL { ?director ghibli:born ?born }
    OPTIONAL { ?director ghibli:nationality ?nationality }
}
LIMIT 10
";

// Query for film (limited to 5 for homepage)
$filmsQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>
PREFIX movie: <http://ghibliwiki.org/movie/>

SELECT DISTINCT ?title ?releaseYear ?posterURL
WHERE {
    ?film a ghibli:Film ;
          ghibli:title ?title ;
          ghibli:releaseYear ?releaseYear ;
          ghibli:posterURL ?posterURL .
}
ORDER BY ?releaseYear
LIMIT 5
";

// Query for Short Films (limited to 5)
$shortsQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>
PREFIX short: <http://ghibliwiki.org/short/>

SELECT DISTINCT ?title ?releaseYear ?posterURL
WHERE {
    ?short a ghibli:ShortFilm ;
           ghibli:title ?title ;
           ghibli:releaseYear ?releaseYear .
    
    OPTIONAL { ?short ghibli:posterURL ?posterURL }
}
ORDER BY ?releaseYear
LIMIT 5
";

// Query for Series
$seriesQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>
PREFIX series: <http://ghibliwiki.org/series/>

SELECT DISTINCT ?title ?releaseYear ?posterURL
WHERE {
    ?series a ghibli:Series ;
            ghibli:title ?title ;
            ghibli:releaseYear ?releaseYear .
    
    OPTIONAL { ?series ghibli:posterURL ?posterURL }
}
ORDER BY ?releaseYear
";

// Execute all queries
$directorsData = executeQuery($directorsQuery);
$filmsData = executeQuery($filmsQuery);
$shortsData = executeQuery($shortsQuery);
$seriesData = executeQuery($seriesQuery);

// Extract results
$directors = isset($directorsData['results']['bindings']) ? $directorsData['results']['bindings'] : [];
$films = isset($filmsData['results']['bindings']) ? $filmsData['results']['bindings'] : [];
$shorts = isset($shortsData['results']['bindings']) ? $shortsData['results']['bindings'] : [];
$series = isset($seriesData['results']['bindings']) ? $seriesData['results']['bindings'] : [];


// Include navbar
include 'navbar.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="style.css" />
  <title>Ghibli Library</title>
</head>
<body>

  <!-- HERO SECTION -->
  <section class="hero">
    <!-- Left Text Card -->
    <div class="hero-text-card">
      <h1>Ghibli Library</h1>
      <p>
        Welcome to the ultimate Studio Ghibli database! Explore the magical world of 
        Studio Ghibli, from timeless classics to modern masterpieces. Discover films, 
        characters, directors, and more from one of the most beloved animation studios 
        in the world. Whether you're a longtime fan or new to Ghibli's enchanting 
        storytelling, you'll find everything you need to dive deep into their incredible 
        catalog of work.
      </p>
    </div>

    <!-- Right Image + Search -->
    <div class="hero-right">
      <div class="search-box">
        <img src="assets/Search.png" class="search-icon" alt="Search Icon">
        <form action="search.php" method="POST">
          <input type="text" name="search" placeholder="What You Want to Find?" required>
        </form>
      </div>
    </div>
  </section>

  <!-- CONTENT CONTAINER -->
  <main class="content-container">

    <!-- DIRECTOR SECTION -->
    <section class="section-block" id="director">
      <h2>Director</h2>
      <div class="slider-wrapper">
        <button class="slide-btn slide-left" data-slider="directorSlider">‹</button>
        <div class="content-slider" id="directorSlider">
          
          <?php if (!empty($directors)): ?>
            <?php foreach ($directors as $director): ?>
              <article class="card">
                <a href="director-detail.php?name=<?php echo urlencode($director['name']['value']); ?>">
                  <?php 
                  $directorImageURL = isset($director['imageURL']['value']) ? $director['imageURL']['value'] : '';
                  ?>
                  
                  <?php if (!empty($directorImageURL)): ?>
                    <img src="image-proxy.php?url=<?php echo urlencode($directorImageURL); ?>" 
                    class="card-img director-img" 
                    alt="<?php echo htmlspecialchars($director['name']['value']); ?>"
                    onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'director-placeholder\'><span class=\'director-initial\'><?php echo strtoupper(substr($director['name']['value'], 0, 1)); ?></span></div>';">
                  <?php else: ?>
                    <div class="director-placeholder">
                      <span class="director-initial">
                        <?php echo strtoupper(substr($director['name']['value'], 0, 1)); ?>
                      </span>
                    </div>
                  <?php endif; ?>
                  
                  <p class="card-title"><?php echo htmlspecialchars($director['name']['value']); ?></p>
                </a>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="no-data">No directors found.</p>
          <?php endif; ?>

        </div>
        <button class="slide-btn slide-right" data-slider="directorSlider">›</button>
      </div>
    </section>

    <!-- FILMS SECTION -->
    <section class="section-block" id="films">
      <h2>Films</h2>
      <div class="content-grid">
        
        <?php if (!empty($films)): ?>
          <?php foreach ($films as $film): ?>
            <article class="grid-card">
              <a href="detail.php?name=<?php echo urlencode($film['title']['value']); ?>&type=film"> 
                <?php 
                $filmPosterURL = isset($film['posterURL']['value']) ? $film['posterURL']['value'] : '';
                ?>
                
                <?php if (!empty($filmPosterURL)): ?>
                  <img src="image-proxy.php?url=<?php echo urlencode($filmPosterURL); ?>" 
                  class="card-img" 
                  alt="<?php echo htmlspecialchars($film['title']['value']); ?>"
                  onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'placeholder-image\'><span>🎬</span><p style=\'font-size: 0.8rem; margin-top: 10px;\'>Image Failed</p></div>';">
                <?php else: ?>
                  <div class="placeholder-image">
                    <span>🎬</span>
                    <p style="font-size: 0.8rem; margin-top: 10px;">No Image</p>
                  </div>
                <?php endif; ?>
                
                <p class="card-title">
                  <?php echo htmlspecialchars($film['title']['value']); ?><br>
                  (<?php echo htmlspecialchars($film['releaseYear']['value']); ?>)
                </p>
              </a>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="no-data">No films found.</p>
        <?php endif; ?>

      </div>
      <a href="content/films.php" class="more-btn">More >></a>
    </section>

    <!-- SHORTS SECTION -->
    <section class="section-block" id="shorts">
      <h2>Shorts</h2>
      <div class="content-grid">
        
        <?php if (!empty($shorts)): ?>
          <?php foreach ($shorts as $short): ?>
            <article class="grid-card">
              <a href="detail.php?name=<?php echo urlencode($short['title']['value']); ?>&type=short"> 
                <?php 
                $shortPosterURL = isset($short['posterURL']['value']) ? $short['posterURL']['value'] : '';
                ?>
                
                <?php if (!empty($shortPosterURL)): ?>
                  <img src="image-proxy.php?url=<?php echo urlencode($shortPosterURL); ?>" 
                  class="card-img" 
                  alt="<?php echo htmlspecialchars($short['title']['value']); ?>"
                  onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'placeholder-image\'><span>🎬</span><p style=\'font-size: 0.8rem; margin-top: 10px;\'>Image Failed</p></div>';">
                <?php else: ?>
                  <div class="placeholder-image">
                    <span>🎬</span>
                    <p style="font-size: 0.8rem; margin-top: 10px;">No Image</p>
                  </div>
                <?php endif; ?>
                
                <p class="card-title">
                  <?php echo htmlspecialchars($short['title']['value']); ?><br>
                  (<?php echo htmlspecialchars($short['releaseYear']['value']); ?>)
                </p>
              </a>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="no-data">No short films found.</p>
        <?php endif; ?>

      </div>
      <a href="content/shorts.php" class="more-btn">More >></a>
    </section>

    <!-- SERIES SECTION -->
    <section class="section-block" id="series">
      <h2>Series</h2>
      <div class="content-grid">
        
        <?php if (!empty($series)): ?>
          <?php foreach ($series as $ser): ?>
            <article class="grid-card">
              <a href="detail.php?name=<?php echo urlencode($ser['title']['value']); ?>&type=series">
                <?php 
                $seriesPosterURL = isset($ser['posterURL']['value']) ? $ser['posterURL']['value'] : '';
                ?>
                
                <?php if (!empty($seriesPosterURL)): ?>
                  <img src="image-proxy.php?url=<?php echo urlencode($seriesPosterURL); ?>" 
                  class="card-img" 
                  alt="<?php echo htmlspecialchars($ser['title']['value']); ?>"
                  onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'placeholder-image\'><span>📺</span><p style=\'font-size: 0.8rem; margin-top: 10px;\'>Image Failed</p></div>';">
                <?php else: ?>
                  <div class="placeholder-image">
                    <span>📺</span>
                    <p style="font-size: 0.8rem; margin-top: 10px;">No Image</p>
                  </div>
                <?php endif; ?>
                
                <p class="card-title">
                  <?php echo htmlspecialchars($ser['title']['value']); ?><br>
                  (<?php echo htmlspecialchars($ser['releaseYear']['value']); ?>)
                </p>
              </a>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="no-data">No series found.</p>
        <?php endif; ?>

      </div>
    </section>

  </main>

  <script src="index.js"></script>
  
</body>
</html>