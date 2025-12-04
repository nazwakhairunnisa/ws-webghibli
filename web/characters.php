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

// Query for all Characters
$charactersQuery = "
PREFIX ghibli: <http://ghibliwiki.org/ontology#>
PREFIX char: <http://ghibliwiki.org/character/>

SELECT DISTINCT ?name ?imageURL ?age ?gender ?description
WHERE {
    ?character a ghibli:Character ;
               ghibli:name ?name .
    
    OPTIONAL { ?character ghibli:imageURL ?imageURL }
    OPTIONAL { ?character ghibli:age ?age }
    OPTIONAL { ?character ghibli:gender ?gender }
    OPTIONAL { ?character ghibli:description ?description }
}
";

$charactersData = executeQuery($charactersQuery);
$characters = isset($charactersData['results']['bindings']) ? $charactersData['results']['bindings'] : [];

// Include navbar
include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- PLAYFAIR DISPLAY -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet" />

  <title>Characters - Ghibli Library</title>

  <style>
    body {
      background: #f5f5f5;
      padding: 25px;
      font-family: Arial, sans-serif;
      margin: 0;
    }

    /* HEADER SECTION WITH TITLE AND SORT BUTTONS */
    .header-section {
      max-width: 1200px;
      margin: 80px auto 30px auto;
      padding: 0 20px;
    }

    h1 {
      text-align: center;
      font-family: 'Playfair Display', serif;
      margin-bottom: 15px;
      font-size: 2.5rem;
      color: #03695E;
    }

    .sort-buttons {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 10px;
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
      border-color: #03695E;
      color: #03695E;
    }

    .sort-btn.active {
      background: #03695E;
      border-color: #03695E;
      color: white;
    }

    /* GRID  */
    .characters-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 40px;
      max-width: 1200px;
      margin: auto;
      padding: 10px 20px;
    }

    /* CARD */
    .char-card {
      background: white;
      border-radius: 12px;
      padding: 0;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: 0.25s ease;
      cursor: pointer;
      overflow: hidden;
      text-decoration: none;
      color: inherit;
    }

    .char-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
    }

    /* GAMBAR FULL KOTAK */
    .img-box {
      width: 100%;
      aspect-ratio: 1 / 1;
      overflow: hidden;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .char-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: 0.3s ease;
    }

    /* Hover zoom */
    .char-card:hover .char-img {
      transform: scale(1.08);
    }

    /* Placeholder for characters without images */
    .char-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 80px;
      color: white;
      font-weight: bold;
      font-family: 'Playfair Display', serif;
    }

    .char-name {
      padding: 12px 15px 18px 15px;
      width: 100%;
      text-align: center;
      font-family: 'Playfair Display', serif;
      font-size: 18px;
      font-weight: 600;
      color: #111;
      letter-spacing: 0.5px;
      line-height: 1.3;
    }

    /* No data message */
    .no-data {
      text-align: center;
      padding: 60px 20px;
      color: #999;
      font-size: 1.2rem;
      font-family: 'Playfair Display', serif;
      grid-column: 1 / -1;
    }

    /* Loading state */
    .loading {
      text-align: center;
      padding: 60px 20px;
      color: #03695E;
      font-size: 1.2rem;
      font-family: 'Playfair Display', serif;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .characters-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 20px;
      }

      .header-section {
        margin-top: 100px;
      }

      h1 {
        font-size: 2rem;
      }

      .char-name {
        font-size: 16px;
      }

      .sort-buttons {
        justify-content: center;
      }
    }
  </style>
</head>

<body>

  <div class="header-section">
    <h1>Studio Ghibli Characters</h1>
    <div class="sort-buttons">
      <button class="sort-btn active" onclick="sortCharacters('asc')">A-Z</button>
      <button class="sort-btn" onclick="sortCharacters('desc')">Z-A</button>
    </div>
  </div>

  <div class="characters-grid" id="charactersGrid">
    <?php if (!empty($characters)): ?>
      <?php foreach ($characters as $character): ?>
        <?php 
        $charImageURL = isset($character['imageURL']['value']) ? $character['imageURL']['value'] : '';
        $charName = htmlspecialchars($character['name']['value']);
        ?>
        
        <a href="character-detail.php?name=<?php echo urlencode($character['name']['value']); ?>" 
           class="char-card" 
           data-name="<?php echo $charName; ?>">
          <div class="img-box">
            <?php if (!empty($charImageURL)): ?>
              <img src="image-proxy.php?url=<?php echo urlencode($charImageURL); ?>" 
                   class="char-img" 
                   alt="<?php echo $charName; ?>"
                   onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'char-placeholder\'><?php echo strtoupper(substr($charName, 0, 1)); ?></div>';">
            <?php else: ?>
              <div class="char-placeholder">
                <?php echo strtoupper(substr($charName, 0, 1)); ?>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="char-name"><?php echo $charName; ?></div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="no-data">No characters found.</p>
    <?php endif; ?>
  </div>

  <script>
    function sortCharacters(order) {
      const grid = document.getElementById('charactersGrid');
      const cards = Array.from(document.querySelectorAll('.char-card'));
      
      // Update active button
      document.querySelectorAll('.sort-btn').forEach(btn => btn.classList.remove('active'));
      event.target.classList.add('active');
      
      // Sort cards
      cards.sort((a, b) => {
        const nameA = a.getAttribute('data-name').toLowerCase();
        const nameB = b.getAttribute('data-name').toLowerCase();
        return order === 'asc' ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
      });
      
      // Re-append sorted cards
      cards.forEach(card => grid.appendChild(card));
    }
  </script>

</body>
</html>