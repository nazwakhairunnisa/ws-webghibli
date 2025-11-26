<?php
$name = isset($_GET['name']) ? $_GET['name'] : '';

if (empty($name)) {
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

// Director details query
$directorQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?name ?born ?birthYear ?nationality ?description ?history ?imageURL
WHERE {
    ?director a ghibli:Director ;
              ghibli:name ?name .
    
    OPTIONAL { ?director ghibli:born ?born }
    OPTIONAL { ?director ghibli:birthYear ?birthYear }
    OPTIONAL { ?director ghibli:nationality ?nationality }
    OPTIONAL { ?director ghibli:description ?description }
    OPTIONAL { ?director ghibli:history ?history }
    OPTIONAL { ?director ghibli:imageURL ?imageURL }
    
    FILTER (regex(?name, \"$name\", \"i\"))
}
";

// Films directed by this director
$filmsQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?title ?releaseYear ?posterURL ?type
WHERE {
    ?director ghibli:name ?directorName ;
              ghibli:directs ?work .
    
    ?work ghibli:title ?title .
    
    OPTIONAL { ?work ghibli:releaseYear ?releaseYear }
    OPTIONAL { ?work ghibli:posterURL ?posterURL }
    
    # Determine type
    {
        ?work a ghibli:Film .
        BIND('Film' AS ?type)
    }
    UNION
    {
        ?work a ghibli:Series .
        BIND('Series' AS ?type)
    }
    UNION
    {
        ?work a ghibli:ShortFilm .
        BIND('Short Film' AS ?type)
    }
    
    FILTER (regex(?directorName, \"$name\", \"i\"))
}
ORDER BY ?releaseYear
";

$directorData = executeQuery($directorQuery);
$filmsData = executeQuery($filmsQuery);

$director = isset($directorData['results']['bindings'][0]) ? $directorData['results']['bindings'][0] : null;
$films = isset($filmsData['results']['bindings']) ? $filmsData['results']['bindings'] : [];

// Include navbar
include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $director ? htmlspecialchars($director['name']['value']) : 'Director'; ?> - Ghibli Library</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="character-detail.css">
  <link rel="stylesheet" href="style.css">
</head>

<body>

<?php if ($director): ?>
  <section class="green-wrapper">
    <div class="white-card">

      <h2>Director</h2>
      <h3 class="char-name"><?php echo htmlspecialchars($director['name']['value']); ?></h3>

      <div class="char-detail-box">
        <?php 
        $imageURL = isset($director['imageURL']['value']) ? $director['imageURL']['value'] : '';
        ?>
        <?php if (!empty($imageURL)): ?>
          <img src="image-proxy.php?url=<?php echo urlencode($imageURL); ?>" 
               class="char-detail-img"
               onerror="this.src='assets/no-image.png'">
        <?php else: ?>
          <div class="char-detail-img-placeholder">
            <span>🎬</span>
          </div>
        <?php endif; ?>

        <div class="char-detail-info">
          <?php if (isset($director['born'])): ?>
            <div class="row">
              <strong>Born</strong>
              <span class="colon">:</span>
              <span><?php echo htmlspecialchars($director['born']['value']); ?></span>
            </div>
          <?php endif; ?>

          <?php if (isset($director['nationality'])): ?>
            <div class="row">
              <strong>Nationality</strong>
              <span class="colon">:</span>
              <span><?php echo htmlspecialchars($director['nationality']['value']); ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (isset($director['description'])): ?>
        <h2 style="margin-top:30px;">About</h2>
        <p class="char-desc-full"><?php echo nl2br(htmlspecialchars($director['description']['value'])); ?></p>
      <?php endif; ?>

      <?php if (isset($director['history'])): ?>
        <h2 style="margin-top:30px;">Biography</h2>
        <p class="char-desc-full"><?php echo nl2br(htmlspecialchars(substr($director['history']['value'], 0, 1500))); ?>
        <?php if (strlen($director['history']['value']) > 1500): ?>
          ...
        <?php endif; ?>
        </p>
      <?php endif; ?>

      <!-- Works by this director -->
      <?php if (!empty($films)): ?>
        <h2 style="margin-top:40px;">Directed Works</h2>
        
        <div class="recommend-wrapper">
          <?php foreach ($films as $film): ?>
            <div class="rec-card">
              <?php
                $typeParam = 'film';
                if ($film['type']['value'] == 'Series') $typeParam = 'series';
                if ($film['type']['value'] == 'Short Film') $typeParam = 'short';
              ?>
              <a href="detail.php?name=<?php echo urlencode($film['title']['value']); ?>&type=<?php echo $typeParam; ?>">
                <?php 
                $posterURL = isset($film['posterURL']['value']) ? $film['posterURL']['value'] : '';
                ?>
                <?php if (!empty($posterURL)): ?>
                  <img src="image-proxy.php?url=<?php echo urlencode($posterURL); ?>" 
                       class="rec-img"
                       onerror="this.src='assets/no-image.png'">
                <?php else: ?>
                  <div class="rec-img-placeholder">
                    <span>🎬</span>
                  </div>
                <?php endif; ?>
                <div class="rec-name">
                  <?php echo htmlspecialchars($film['title']['value']); ?>
                  <?php if (isset($film['releaseYear'])): ?>
                    <br><small>(<?php echo htmlspecialchars($film['releaseYear']['value']); ?>)</small>
                  <?php endif; ?>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="text-align: center; margin-top: 40px;">
        <a href="index.php" class="back-btn">← Back to Home</a>
      </div>

    </div>
  </section>

<?php else: ?>
  <section class="green-wrapper" style="min-height: 100vh; display: flex; align-items: center;">
    <div class="white-card" style="text-align: center;">
      <h2>Director Not Found</h2>
      <a href="index.php" class="back-btn">← Back to Home</a>
    </div>
  </section>
<?php endif; ?>

</body>
</html>