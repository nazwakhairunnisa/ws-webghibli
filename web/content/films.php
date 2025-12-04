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

// Query for Films
$filmsQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>
PREFIX movie: <http://ghibliwiki.org/movie/>

SELECT DISTINCT ?title ?releaseYear ?posterURL
WHERE {
    ?film a ghibli:Film ;
          ghibli:title ?title ;
          ghibli:releaseYear ?releaseYear .
          OPTIONAL { ?film ghibli:posterURL ?posterURL }
}
ORDER BY ?releaseYear
";

$filmsData = executeQuery($filmsQuery);
$films = isset($filmsData['results']['bindings']) ? $filmsData['results']['bindings'] : [];

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
    <style>
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .sort-buttons {
            display: flex;
            gap: 8px;
        }
        .sort-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .sort-btn:hover {
            border-color: #4caf50;
            color: #4caf50;
        }
        .sort-btn.active {
            background: #4caf50;
            border-color: #4caf50;
            color: white;
        }
    </style>
</head>

<body>
    <!-- MAIN CONTENT -->
    <main class="container">
        <section class="section-block" id="films">
            <div class="section-header">
                <h2>Films</h2>
                <div class="sort-buttons">
                    <button class="sort-btn active" onclick="sortFilms('asc')">A-Z</button>
                    <button class="sort-btn" onclick="sortFilms('desc')">Z-A</button>
                </div>
            </div>

            <div class="film-grid" id="filmGrid">
                <?php if (!empty($films)): ?>
                <?php foreach ($films as $film): ?>
                    <article class="film-card" data-title="<?php echo htmlspecialchars($film['title']['value']); ?>">
                    <a href="../detail.php?name=<?php echo urlencode($film['title']['value']); ?>&type=film" style="text-decoration: none; color: #333;">
                        <?php 
                        $filmPosterURL = isset($film['posterURL']['value']) ? $film['posterURL']['value'] : '';
                        ?>
                        
                        <?php if (!empty($filmPosterURL)): ?>
                        <img src="../image-proxy.php?url=<?php echo urlencode($filmPosterURL); ?>" 
                        class="film-img" 
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
        </section>
    </main>

    <script src="../app.js"></script>
    <script>
        function sortFilms(order) {
            const grid = document.getElementById('filmGrid');
            const cards = Array.from(document.querySelectorAll('.film-card'));
            
            // Update active button
            document.querySelectorAll('.sort-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Sort cards
            cards.sort((a, b) => {
                const titleA = a.getAttribute('data-title').toLowerCase();
                const titleB = b.getAttribute('data-title').toLowerCase();
                return order === 'asc' ? titleA.localeCompare(titleB) : titleB.localeCompare(titleA);
            });
            
            // Re-append sorted cards
            cards.forEach(card => grid.appendChild(card));
        }
    </script>
</body>
</html>