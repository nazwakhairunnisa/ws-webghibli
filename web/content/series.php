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

$seriesData = executeQuery($seriesQuery);
$series = isset($seriesData['results']['bindings']) ? $seriesData['results']['bindings'] : [];


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
            <h2>Series</h2>
            <div class="film-grid">
                
                <?php if (!empty($series)): ?>
                <?php foreach ($series as $ser): ?>
                    <article class="grid-card">
                    <a href="../detail.php?name=<?php echo urlencode($ser['title']['value']); ?>&type=series">
                        <?php 
                        $seriesPosterURL = isset($ser['posterURL']['value']) ? $ser['posterURL']['value'] : '';
                        ?>
                        
                        <?php if (!empty($seriesPosterURL)): ?>
                        <img src="../image-proxy.php?url=<?php echo urlencode($seriesPosterURL); ?>" 
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

    <script src="../app.js"></script>
</body>
</html>