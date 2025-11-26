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

// Query for Short Films
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
";

$shortsData = executeQuery($shortsQuery);
$shorts = isset($shortsData['results']['bindings']) ? $shortsData['results']['bindings'] : [];

// Include navbar
include '../navbar.php';

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ghibli - Film</title>

    <link rel="stylesheet" href="../navbar.css">
    <link rel="stylesheet" href="content.css">
</head>

<body>


    <!-- MAIN CONTENT -->
    <main class="container">

        <section class="section-block" id="films">
            <h2>Short Films</h2>
            <div class="film-grid">
                
                <?php if (!empty($shorts)): ?>
                <?php foreach ($shorts as $short): ?>
                    <article class="film-card">
                    <a href="../detail.php?name=<?php echo urlencode($short['title']['value']); ?>&type=short" style="text-decoration: none;"> 
                        <?php 
                        $shortPosterURL = isset($short['posterURL']['value']) ? $short['posterURL']['value'] : '';
                        ?>
                        
                        <?php if (!empty($shortPosterURL)): ?>
                        <img src="../image-proxy.php?url=<?php echo urlencode($shortPosterURL); ?>" 
                        class="film-img" 
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
                <p class="no-data">No shorts found.</p>
                <?php endif; ?>

            </div>
        
            </section>

    </main>

    <script src="../app.js"></script>
</body>
</html>