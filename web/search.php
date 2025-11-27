<?php
// Get search input
$searchInput = isset($_POST['search']) ? $_POST['search'] : '';

if (empty($searchInput)) {
    header('Location: index.php');
    exit;
}

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

// Search across Films, Series, Shorts
$searchQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?type ?title ?releaseYear ?posterURL ?imageURL
WHERE {
    {
        # Search Films
        ?item a ghibli:Film ;
              ghibli:title ?title ;
              ghibli:releaseYear ?releaseYear ;
              ghibli:posterURL ?posterURL .
        BIND('Film' AS ?type)
        FILTER (regex(?title, '$searchInput', 'i'))
    }
    UNION
    {
        # Search Series
        ?item a ghibli:Series ;
              ghibli:title ?title ;
              ghibli:releaseYear ?releaseYear .
        OPTIONAL { ?item ghibli:posterURL ?posterURL }
        BIND('Series' AS ?type)
        FILTER (regex(?title, '$searchInput', 'i'))
    }
    UNION
    {
        # Search Short Films
        ?item a ghibli:ShortFilm ;
              ghibli:title ?title ;
              ghibli:releaseYear ?releaseYear .
        OPTIONAL { ?item ghibli:posterURL ?posterURL }
        BIND('Short Film' AS ?type)
        FILTER (regex(?title, '$searchInput', 'i'))
    }
    UNION
    {
        # Search Characters
        ?item a ghibli:Character ;
              ghibli:name ?title ;
              ghibli:imageURL ?imageURL .
        BIND('Character' AS ?type)
        BIND('' AS ?releaseYear)
        BIND(?imageURL AS ?posterURL)
        FILTER (regex(?title, '$searchInput', 'i'))
    }
    UNION
    {
        # Search Directors
        ?item a ghibli:Director ;
              ghibli:name ?title .
        OPTIONAL { ?item ghibli:imageURL ?imageURL }
        BIND('Director' AS ?type)
        BIND('' AS ?releaseYear)
        FILTER (regex(?title, '$searchInput', 'i'))
    }
}
ORDER BY ?type ?releaseYear
";

$searchData = executeQuery($searchQuery);
$results = isset($searchData['results']['bindings']) ? $searchData['results']['bindings'] : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="style.css" />
  <title>Search Results - Ghibli Library</title>
</head>
<body>

  <!-- NAVBAR -->
  <header class="navbar">
    <div class="logo">Ghibli<br>Studio</div>
    <nav class="nav-links">
      <a href="index.php">Home</a>
      <a href="directors.php">Director</a>
      <div class="dropdown">
        <button class="dropbtn">Content ▾</button>
        <div class="dropdown-content">
          <a href="films.php">Film</a>
          <a href="shorts.php">Shorts</a>
          <a href="series.php">Series</a>
        </div>
      </div>
      <a href="characters.php">Characters</a>
    </nav>
  </header>

  <main class="content-container" style="margin-top: 100px;">
    <section class="section-block">
      <h2>Search Results for "<?php echo htmlspecialchars($searchInput); ?>"</h2>
      
      <?php if (!empty($results)): ?>
        <div class="content-grid">
          <?php foreach ($results as $result): ?>
            <article class="grid-card">
              <div class="type-badge"><?php echo htmlspecialchars($result['type']['value']); ?></div>
              
              <?php 
                // Determine which property to use based on type
                $imageUrl = '';
                if ($result['type']['value'] == 'Director' || $result['type']['value'] == 'Character') {
                  // For Directors and Characters, try imageURL first, then posterURL
                  $imageUrl = isset($result['imageURL']['value']) ? $result['imageURL']['value'] : '';
                  if (empty($imageUrl)) {
                    $imageUrl = isset($result['posterURL']['value']) ? $result['posterURL']['value'] : '';
                  }
                } else {
                  // For Films, Series, Shorts - use posterURL
                  $imageUrl = isset($result['posterURL']['value']) ? $result['posterURL']['value'] : '';
                }
              ?>

              <?php if (!empty($imageUrl)): ?>
                  <img src="image-proxy.php?url=<?php echo urlencode($imageUrl); ?>" 
                      class="card-img" 
                      alt="<?php echo htmlspecialchars($result['title']['value']); ?>"
                      onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'placeholder-image\'><span><?php echo $result['type']['value'] == 'Director' ? '🎬' : ($result['type']['value'] == 'Character' ? '👤' : '🎭'); ?></span></div>';">
              <?php else: ?>
                  <div class="placeholder-image">
                      <span>
                          <?php 
                            echo $result['type']['value'] == 'Director' ? '🎬' : 
                                ($result['type']['value'] == 'Character' ? '👤' : '🎭'); 
                          ?>
                      </span>
                  </div>
              <?php endif; ?>
              
              <p class="card-title">
                <?php echo htmlspecialchars($result['title']['value']); ?>
                <?php if (!empty($result['releaseYear']['value'])): ?>
                  <br>(<?php echo htmlspecialchars($result['releaseYear']['value']); ?>)
                <?php endif; ?>
              </p>

              <?php
                // Determine the correct detail page based on type
                $detailPage = 'detail.php';
                $detailParams = 'name=' . urlencode($result['title']['value']);

                if ($result['type']['value'] == 'Director') {
                    $detailPage = 'director-detail.php';
                    $detailParams = 'name=' . urlencode($result['title']['value']);
                } elseif ($result['type']['value'] == 'Character') {
                    $detailPage = 'character-detail.php';
                    $detailParams = 'name=' . urlencode($result['title']['value']);
                } else {
                    // For Film, Series, Short Film
                    $typeMap = [
                        'Film' => 'film',
                        'Series' => 'series',
                        'Short Film' => 'short'
                    ];
                    $typeParam = isset($typeMap[$result['type']['value']]) ? $typeMap[$result['type']['value']] : 'film';
                    $detailParams = 'name=' . urlencode($result['title']['value']) . '&type=' . $typeParam;
                }
              ?>
              
              <a href="<?php echo $detailPage; ?>?<?php echo $detailParams; ?>" 
                 class="btn-view">View Details</a>
            </article>
          <?php endforeach; ?>
        </div>
        
        <p class="results-count">Found <?php echo count($results); ?> result(s)</p>
      <?php else: ?>
        <p class="no-data">No results found for "<?php echo htmlspecialchars($searchInput); ?>"</p>
        <a href="index.php" class="more-btn">← Back to Home</a>
      <?php endif; ?>
    </section>
  </main>

  <script src="index.js"></script>
</body>
</html>