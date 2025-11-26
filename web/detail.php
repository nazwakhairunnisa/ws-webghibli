<?php
$name = isset($_GET['name']) ? $_GET['name'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'film';

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

// Determine class type and specific fields
$classType = 'ghibli:Film';
$additionalFields = '';

if ($type == 'series') {
    $classType = 'ghibli:Series';
    $additionalFields = '
        OPTIONAL { ?item ghibli:numberOfEpisodes ?episodes }
        OPTIONAL { ?item ghibli:producedBy ?studioItem . 
                   ?studioItem ghibli:name ?studio }
    ';
} elseif ($type == 'short') {
    $classType = 'ghibli:ShortFilm';
}

// Main detail query
$detailQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?title ?releaseYear ?duration ?description ?synopsis ?posterURL 
       ?directorName ?genre ?episodes ?studio ?plot
WHERE {
    ?item a $classType ;
          ghibli:title ?title .
    
    OPTIONAL { ?item ghibli:releaseYear ?releaseYear }
    OPTIONAL { ?item ghibli:duration ?duration }
    OPTIONAL { ?item ghibli:description ?description }
    OPTIONAL { ?item ghibli:synopsis ?synopsis }
    OPTIONAL { ?item ghibli:posterURL ?posterURL }
    OPTIONAL { ?item ghibli:plot ?plot }
    
    OPTIONAL { 
        ?item ghibli:hasDirector ?director .
        ?director ghibli:name ?directorName 
    }
    
    OPTIONAL {
        ?item ghibli:hasGenre ?genreItem .
        ?genreItem ghibli:name ?genre
    }
    
    $additionalFields
    
    FILTER (regex(?title, \"$name\", \"i\"))
}
";

// Characters query (only for films, series might not have characters)
$charactersQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>

SELECT DISTINCT ?characterName ?age ?gender ?imageURL ?description
WHERE {
    ?item ghibli:title ?title ;
          ghibli:hasCharacter ?character .
    
    ?character ghibli:name ?characterName .
    
    OPTIONAL { ?character ghibli:imageURL ?imageURL }
    OPTIONAL { ?character ghibli:age ?age }
    OPTIONAL { ?character ghibli:gender ?gender }
    OPTIONAL { ?character ghibli:description ?description }
    
    FILTER (regex(?title, \"$name\", \"i\"))
}
";

$detailData = executeQuery($detailQuery);
$charactersData = executeQuery($charactersQuery);

$details = isset($detailData['results']['bindings']) ? $detailData['results']['bindings'] : [];
$characters = isset($charactersData['results']['bindings']) ? $charactersData['results']['bindings'] : [];

$detail = !empty($details) ? $details[0] : null;

// Collect genres
$genres = [];
foreach ($details as $row) {
    if (isset($row['genre']['value'])) {
        $genres[] = $row['genre']['value'];
    }
}
$genres = array_unique($genres);

// Hero image mappings
$heroImages = [
    // Films
    "Castle in the Sky" => "castle.jpg",
    "Earwig and the Witch" => "earwig.jpg",
    "From Up on Poppy Hill" => "poppy.jpg",
    "Grave of the Fireflies" => "fireflies.jpg",
    "Howl's Moving Castle" => "howl.jpg",
    "Kiki's Delivery Service" => "kiki.jpg",
    "Mary and the Witch's Flower" => "mary.jpg",
    "Modest Heroes" => "modest.jpg",
    "My Neighbor Totoro" => "totoro.jpg",
    "My Neighbors the Yamadas" => "yamadas.jpg",
    "Nausicaä of the Valley of the Wind" => "nausicaa.jpg",
    "Ocean Waves" => "ocean.jpg",
    "Only Yesterday" => "yesterday.jpg",
    "Panda! Go, Panda!" => "panda.jpg",
    "Pom Poko" => "pompoko.jpg",
    "Ponyo" => "ponyo.jpg",
    "Porco Rosso" => "porco.jpg",
    "Princess Mononoke" => "mononoke.jpg",
    "Spirited Away" => "spirited.jpg",
    "Tales from Earthsea" => "earthsea.jpg",
    "The Boy and the Heron" => "heron.jpg",
    "The Castle of Cagliostro" => "cagliostro.jpg",
    "The Cat Returns" => "catreturns.jpg",
    "The Imaginary" => "imaginary.jpg",
    "The Red Turtle" => "redturtle.jpg",
    "The Secret World of Arrietty" => "arrietty.jpg",
    "The Tale of the Princess Kaguya" => "kaguya.jpg",
    "The Wind Rises" => "windrises.jpg",
    
    // Series
    "Ronja, the Robber's Daughter" => "ronja.jpg",
    "Sherlock Hound" => "hound.jpg",
    "Film Guru Guru" => "guru.jpg",
    
    // Shorts
    "3000 Leagues in Search of Mother" => "3000leagues.jpg",
    "A Sumo Wrestler's Tail" => "sumo.jpg",
    "Boro the Caterpillar" => "boro.jpg",
    "Ghiblies" => "ghiblies.jpg",
    "Ghiblies Episode 2" => "ghiblies2.jpg",
    "Giant God Warrior Appears in Tokyo" => "giantgod.jpg",
    "Hoshi o Katta Hi" => "hoshi.jpg",
    "Iblard Jikan" => "iblard.jpg",
    "Imaginary Flying Machines" => "flying.jpg",
    "Koro's Big Day Out" => "koro.jpg",
    "Kujiratori" => "kujiratori.jpg",
    "Looking for a Home" => "home.jpg",
    "Mei and the Kittenbus" => "mei.jpg",
    "Mr. Dough and the Egg Princess" => "egg.jpg",
    "On Your Mark" => "onyourmark.jpg",
    "Portable Airport" => "airport.jpg",
    "Red Crow and the Ghost Ship" => "redcrow.jpg",
    "Soratobu Toshikeikaku" => "soratobu.jpg",
    "Space Station No. 9" => "spacestation.jpg",
    "The Invention of Imaginary Machines of Destruction" => "invention.jpg",
    "The Night of Taneyamagahara" => "taneyama.jpg",
    "Treasure Hunting" => "treasure.jpg",
    "Water Spider Monmon" => "monmon.jpg",
    "Zen - Grogu and Dust Bunnies" => "zen.jpg",
];

$heroImage = isset($heroImages[$name]) ? $heroImages[$name] : 'default-hero.jpg';

// Get synopsis/plot
$synopsisText = '';
if (isset($detail['synopsis']['value'])) {
    $synopsisText = $detail['synopsis']['value'];
} elseif (isset($detail['plot']['value'])) {
    $synopsisText = $detail['plot']['value'];
} elseif (isset($detail['description']['value'])) {
    $synopsisText = $detail['description']['value'];
}

// Include navbar
include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="detail.css" />
  <link rel="stylesheet" href="style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <title><?php echo $detail ? htmlspecialchars($detail['title']['value']) : 'Detail'; ?> - Ghibli Library</title>
</head>
<body>

<?php if ($detail): ?>
  <!-- HERO SECTION -->
  <section class="hero-section">
    <img src="assets/hero/<?php echo htmlspecialchars($heroImage); ?>" 
         class="hero-img" 
         alt="<?php echo htmlspecialchars($detail['title']['value']); ?>"
         onerror="this.src='assets/default-hero.jpg'">

    <div class="hero-overlay"></div>

    <div class="hero-flex">
      <h1><?php echo nl2br(htmlspecialchars($detail['title']['value'])); ?></h1>
      <?php if (!empty($synopsisText)): ?>
        <p>
          <?php 
            echo htmlspecialchars(substr($synopsisText, 0, 350));
            if (strlen($synopsisText) > 350) {
              echo ' ... <a href="#synopsis" style="color: #4fc3f7; text-decoration: none; font-weight: bold;">More>></a>';
            }
          ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="hero-overlay-green"></div>
  </section>

  <!-- MAIN CONTENT -->
  <section class="green-wrapper">
    <div class="white-card">
      <!-- POSTER + META -->
      <div class="top-content">
        <?php 
        $posterURL = isset($detail['posterURL']['value']) ? $detail['posterURL']['value'] : '';
        ?>
        <?php if (!empty($posterURL)): ?>
          <img src="image-proxy.php?url=<?php echo urlencode($posterURL); ?>" 
               class="poster" 
               alt="Poster"
               onerror="this.src='assets/no-image.png'">
        <?php else: ?>
          <div class="poster-placeholder">
            <span>🎬</span>
          </div>
        <?php endif; ?>

        <div class="meta">
          <div><strong>Title</strong>: <span><?php echo htmlspecialchars($detail['title']['value']); ?></span></div>
          
          <?php if (isset($detail['releaseYear'])): ?>
            <div><strong>Release Year</strong>: <span><?php echo htmlspecialchars($detail['releaseYear']['value']); ?></span></div>
          <?php endif; ?>
          
          <?php if (isset($detail['directorName'])): ?>
            <div><strong>Director</strong>: <span><?php echo htmlspecialchars($detail['directorName']['value']); ?></span></div>
          <?php endif; ?>
          
          <?php if ($type == 'series' && isset($detail['episodes'])): ?>
            <div><strong>Episodes</strong>: <span><?php echo htmlspecialchars($detail['episodes']['value']); ?></span></div>
          <?php endif; ?>
          
          <?php if ($type == 'series' && isset($detail['studio'])): ?>
            <div><strong>Studio</strong>: <span><?php echo htmlspecialchars($detail['studio']['value']); ?></span></div>
          <?php endif; ?>
          
          <?php if ($type == 'film' && isset($detail['duration'])): ?>
            <div><strong>Duration</strong>: <span><?php echo htmlspecialchars($detail['duration']['value']); ?></span></div>
          <?php endif; ?>
          
          <?php if (!empty($genres)): ?>
            <div><strong>Genre</strong>: <span><?php echo implode(', ', array_map('htmlspecialchars', $genres)); ?></span></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- DESCRIPTION -->
      <?php if (isset($detail['description'])): ?>
        <h2>Description</h2>
        <p><?php echo nl2br(htmlspecialchars($detail['description']['value'])); ?></p>
      <?php endif; ?>

      <!-- SYNOPSIS/PLOT -->
      <?php if (!empty($synopsisText)): ?>
        <h2 id="synopsis"><?php echo ($type == 'series') ? 'Plot' : 'Synopsis'; ?></h2>
        <p><?php echo nl2br(htmlspecialchars($synopsisText)); ?></p>
      <?php endif; ?>

      <!-- CHARACTERS (only for films/series with characters) -->
      <?php if (!empty($characters)): ?>
        <h2>Characters</h2>
        
        <?php foreach ($characters as $character): ?>
          <div class="character-box">
            <h3 class="char-name"><?php echo htmlspecialchars($character['characterName']['value']); ?></h3>

            <div class="char-top">
              <?php 
              $charImageURL = isset($character['imageURL']['value']) ? $character['imageURL']['value'] : '';
              ?>
              <?php if (!empty($charImageURL)): ?>
                <img src="image-proxy.php?url=<?php echo urlencode($charImageURL); ?>" 
                     class="char-img"
                     onerror="this.src='assets/no-image.png'">
              <?php else: ?>
                <div class="char-img-placeholder">
                  <span>👤</span>
                </div>
              <?php endif; ?>

              <div class="char-info">
                <?php if (isset($character['age'])): ?>
                  <div class="row">
                    <strong>Age</strong>
                    <span><?php echo htmlspecialchars($character['age']['value']); ?></span>
                  </div>
                <?php endif; ?>
                
                <?php if (isset($character['gender'])): ?>
                  <div class="row">
                    <strong>Gender</strong>
                    <span><?php echo htmlspecialchars($character['gender']['value']); ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <?php if (isset($character['description'])): ?>
              <p class="char-desc"><?php echo nl2br(htmlspecialchars($character['description']['value'])); ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Back Button -->
      <div style="text-align: center; margin-top: 40px;">
        <a href="index.php" class="back-btn">← Back to Home</a>
      </div>
    </div>
  </section>

<?php else: ?>
  <section class="green-wrapper" style="min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div class="white-card" style="text-align: center; padding: 60px;">
      <h2>Content Not Found</h2>
      <p style="margin: 20px 0;">The content you're looking for doesn't exist.</p>
      <a href="index.php" class="back-btn">← Back to Home</a>
    </div>
  </section>
<?php endif; ?>

</body>
</html>