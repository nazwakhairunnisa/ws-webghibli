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

// Character details query
$characterQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?name ?age ?gender ?imageURL ?description
WHERE {
    ?character a ghibli:Character ;
               ghibli:name ?name .
    
    OPTIONAL { ?character ghibli:imageURL ?imageURL }
    OPTIONAL { ?character ghibli:age ?age }
    OPTIONAL { ?character ghibli:gender ?gender }
    OPTIONAL { ?character ghibli:description ?description }
    
    FILTER (regex(?name, \"$name\", \"i\"))
}
";

// Film this character appears in
$filmQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?filmTitle
WHERE {
    ?character ghibli:name ?name ;
               ghibli:appearsIn ?film .
    
    ?film ghibli:title ?filmTitle .
    
    FILTER (regex(?name, \"$name\", \"i\"))
}
LIMIT 1
";

// Recommended characters (from same film)
$recommendedQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?characterName ?imageURL
WHERE {
    ?mainChar ghibli:name ?mainName ;
              ghibli:appearsIn ?film .
    
    ?film ghibli:hasCharacter ?otherChar .
    
    ?otherChar ghibli:name ?characterName .
    
    OPTIONAL { ?otherChar ghibli:imageURL ?imageURL }
    
    FILTER (regex(?mainName, \"$name\", \"i\"))
    FILTER (?characterName != \"$name\")
}
LIMIT 3
";

$characterData = executeQuery($characterQuery);
$filmData = executeQuery($filmQuery);
$recommendedData = executeQuery($recommendedQuery);

$character = isset($characterData['results']['bindings'][0]) ? $characterData['results']['bindings'][0] : null;
$film = isset($filmData['results']['bindings'][0]) ? $filmData['results']['bindings'][0] : null;
$recommended = isset($recommendedData['results']['bindings']) ? $recommendedData['results']['bindings'] : [];

// Include navbar
include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $character ? htmlspecialchars($character['name']['value']) : 'Character'; ?> - Ghibli Library</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="character-detail.css">
  <link rel="stylesheet" href="style.css">
</head>

<body>

<?php if ($character): ?>
  <section class="green-wrapper">
    <div class="white-card">

      <h2>Character</h2>
      <h3 class="char-name"><?php echo htmlspecialchars($character['name']['value']); ?></h3>

      <div class="char-detail-box">
        <?php 
        $charImageURL = isset($character['imageURL']['value']) ? $character['imageURL']['value'] : '';
        ?>
        <?php if (!empty($charImageURL)): ?>
          <img src="image-proxy.php?url=<?php echo urlencode($charImageURL); ?>" 
               class="char-detail-img"
               onerror="this.src='assets/no-image.png'">
        <?php else: ?>
          <div class="char-detail-img-placeholder">
            <span>👤</span>
          </div>
        <?php endif; ?>

        <div class="char-detail-info">
          <?php if (isset($character['age'])): ?>
            <div class="row">
              <strong>Age</strong>
              <span class="colon">:</span>
              <span><?php echo htmlspecialchars($character['age']['value']); ?></span>
            </div>
          <?php endif; ?>

          <?php if (isset($character['gender'])): ?>
            <div class="row">
              <strong>Gender</strong>
              <span class="colon">:</span>
              <span><?php echo htmlspecialchars($character['gender']['value']); ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (isset($character['description'])): ?>
        <p class="char-desc-full"><?php echo nl2br(htmlspecialchars($character['description']['value'])); ?></p>
      <?php endif; ?>

      <!-- Recommendation -->
      <?php if (!empty($recommended)): ?>
        <h2 style="margin-top:40px;">Recommendation</h2>
        <?php if ($film): ?>
          <p style="margin-left: 20px; color: #666;">Other characters from <?php echo htmlspecialchars($film['filmTitle']['value']); ?></p>
        <?php endif; ?>
        
        <div class="recommend-wrapper">
          <?php foreach ($recommended as $rec): ?>
            <div class="rec-card">
              <a href="character-detail.php?name=<?php echo urlencode($rec['characterName']['value']); ?>">
                <?php 
                $recImageURL = isset($rec['imageURL']['value']) ? $rec['imageURL']['value'] : '';
                ?>
                <?php if (!empty($recImageURL)): ?>
                  <img src="image-proxy.php?url=<?php echo urlencode($recImageURL); ?>" 
                       class="rec-img"
                       onerror="this.src='assets/no-image.png'">
                <?php else: ?>
                  <div class="rec-img-placeholder">
                    <span>👤</span>
                  </div>
                <?php endif; ?>
                <div class="rec-name"><?php echo htmlspecialchars($rec['characterName']['value']); ?></div>
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
      <h2>Character Not Found</h2>
      <a href="index.php" class="back-btn">← Back to Home</a>
    </div>
  </section>
<?php endif; ?>

</body>
</html>