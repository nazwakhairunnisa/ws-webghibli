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
";

// Execute all queries
$directorsData = executeQuery($directorsQuery);

// Extract results
$directors = isset($directorsData['results']['bindings']) ? $directorsData['results']['bindings'] : [];


// Include navbar
include 'navbar.php';

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ghibli - Directors</title>

    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="content/content.css">
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
                <h2>Directors</h2>
                <div class="sort-buttons">
                    <button class="sort-btn active" onclick="sortDirectors('asc')">A-Z</button>
                    <button class="sort-btn" onclick="sortDirectors('desc')">Z-A</button>
                </div>
            </div>

            <div class="film-grid" id="directorsGrid">
                
                <?php if (!empty($directors)): ?>
                <?php foreach ($directors as $director): ?>
                    <article class="grid-card" data-title="<?php echo htmlspecialchars($director['name']['value']); ?>">
                    <a href="director-detail.php?name=<?php echo urlencode($director['name']['value']); ?>&type=director">
                        <?php 
                        $directorImageURL = isset($director['imageURL']['value']) ? $director['imageURL']['value'] : '';
                        ?>
                        
                        <?php if (!empty($directorImageURL)): ?>
                        <img src="image-proxy.php?url=<?php echo urlencode($directorImageURL); ?>" 
                        class="card-img" 
                        alt="<?php echo htmlspecialchars($director['name']['value']); ?>"
                        onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'placeholder-image\'><span>👤</span><p style=\'font-size: 0.8rem; margin-top: 10px;\'>Image Failed</p></div>';">
                        <?php else: ?>
                        <div class="placeholder-image">
                            <span>👤</span>
                            <p style="font-size: 0.8rem; margin-top: 10px;">No Image</p>
                        </div>
                        <?php endif; ?>
                        
                        <p class="card-title">
                        <?php echo htmlspecialchars($director['name']['value']); ?>
                        </p>
                    </a>
                    </article>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="no-data">No directors found.</p>
                <?php endif; ?>

            </div>
        
        </section>

    </main>

    <script src="app.js"></script>
    <script>
        function sortDirectors(order) {
            const grid = document.getElementById('directorsGrid');
            const cards = Array.from(document.querySelectorAll('.grid-card'));
            
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